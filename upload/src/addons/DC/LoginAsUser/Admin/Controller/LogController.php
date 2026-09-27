<?php

declare(strict_types=1);

namespace DC\LoginAsUser\Admin\Controller;

use DC\LoginAsUser\Entity\Session;
use DC\LoginAsUser\Repository\SessionRepository;
use XF\Entity\User as UserEntity;
use XF\Admin\Controller\AbstractController;
use XF\Entity\User;
use XF\Finder\UserFinder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/**
 * The audit log.
 *
 * Gated on core's viewLogs rather than on this add-on's own admin permission. The rows hold a staff
 * username, a member username, a free-text reason and two timestamps - the same class of
 * information as the moderator log, which every other page under Logs > Users gates the same way.
 *
 * XF reserves assertSuperAdmin() for logs/admin because xf_admin_log stores the raw POST payload of
 * every ACP write, which routinely contains SMTP passwords and API keys; that threat model does not
 * apply to a table with no payload column. Gating this page more tightly than the moderator log
 * would also defeat its purpose - an audit trail readable only by the single most privileged
 * account is not an audit trail, it is a diary.
 */
class LogController extends AbstractController
{
	protected const PER_PAGE = 20;

	protected function preDispatchController($action, ParameterBag $params): void
	{
		$this->assertAdminPermission('viewLogs');
	}

	public function actionIndex(ParameterBag $params): AbstractReply
	{
		$sessionRepo = $this->getSessionRepo();

		$page = $this->filterPage();
		$perPage = self::PER_PAGE;

		$finder = $sessionRepo->findLogsForList()
			->limitByPage($page, $perPage);

		$linkFilters = [];

		$actorUserId = $this->filter('actor_user_id', 'uint');
		if ($actorUserId)
		{
			$linkFilters['actor_user_id'] = $actorUserId;
			$finder->forActor($actorUserId);
		}

		// Two different controls for the two ends of the same relationship, on purpose. The actor
		// set is bounded - staff who have used the feature - so a select is both cheap and easier
		// than remembering a name. The target set is every member on the board, so it is an
		// autocomplete.
		$limitTarget = null;
		if ($targetUsername = $this->filter('target_username', 'str'))
		{
			$limitTarget = $this->em()->findOne(UserFinder::class, ['username' => $targetUsername]);

			if (!$limitTarget)
			{
				return $this->error(\XF::phrase('requested_user_not_found'));
			}
		}
		else if ($targetUserId = $this->filter('target_user_id', 'uint'))
		{
			$limitTarget = $this->em()->find(User::class, $targetUserId);

			if (!$limitTarget)
			{
				return $this->error(\XF::phrase('requested_user_not_found'));
			}
		}

		if ($limitTarget)
		{
			$linkFilters['target_user_id'] = $limitTarget->user_id;
			$finder->forTarget($limitTarget->user_id);
		}

		$start = $this->filter('start', 'datetime');
		$end = $this->filter('end', 'datetime');

		if ($start)
		{
			$linkFilters['start'] = $this->app->language()->date($start, 'Y-m-d');
		}

		if ($end)
		{
			$linkFilters['end'] = $this->app->language()->date($end, 'Y-m-d');
		}

		$finder->startedBetween($start ?: null, $end ?: null);

		$activeOnly = $this->filter('active_only', 'bool');
		if ($activeOnly)
		{
			$linkFilters['active_only'] = 1;
			$finder->onlyActive();
		}

		if ($this->isPost())
		{
			// redirect to give a linkable page - the same reason core does it in actionModerator
			return $this->redirect($this->buildLink('login-as-user', null, $linkFilters));
		}

		$viewParams = [
			'entries' => $finder->fetch(),
			'logUsers' => $sessionRepo->getUsersInLog(),

			'actorUserId' => $actorUserId,
			'limitTarget' => $limitTarget,
			'start' => $start,
			'end' => $end,
			'activeOnly' => $activeOnly,

			'page' => $page,
			'perPage' => $perPage,
			'total' => $finder->total(),
			'linkFilters' => $linkFilters,
		];

		return $this->view(
			'DC\LoginAsUser:Log\Listing',
			'dcLoginAsUser_log_list',
			$viewParams
		);
	}

	/**
	 * Row overlay, reached as login-as-user/view/5/ via the 'view' sub-route.
	 */
	public function actionView(ParameterBag $params): AbstractReply
	{
		$sessionId = (int) $params->session_id;

		/** @var Session $entry */
		$entry = $this->assertRecordExists(
			Session::class,
			$sessionId,
			['User', 'Target', 'EndedBy'],
			'dcLoginAsUser_requested_session_not_found'
		);

		return $this->view(
			'DC\LoginAsUser:Log\View',
			'dcLoginAsUser_log_view',
			['entry' => $entry]
		);
	}

	/**
	 * Who is signed in as someone else right now, and the button that stops them.
	 *
	 * Gated on this add-on's own admin permission rather than viewLogs, because ending a session is
	 * not reading a log: it terminates a peer administrator's browsing session outright. Super
	 * admins pass automatically - Admin::hasAdminPermission() returns true for is_super_admin before
	 * consulting the cache - so out of the box this behaves exactly like assertSuperAdmin(), with
	 * the difference that a board can delegate it without minting another super admin.
	 *
	 * It lives on this controller rather than its own because XenForo routes a whole admin prefix to
	 * one controller: the empty-sub_name row swallows every trailing segment, so a second controller
	 * would only ever be reachable with a trailing slash. Core's own LogController does the same
	 * thing, and puts its per-action gates (assertSuperAdmin on actionAdmin) inside the actions.
	 */
	public function actionSessions(ParameterBag $params): AbstractReply
	{
		$this->assertAdminPermission('dcLoginAsUser');

		$sessions = $this->getSessionRepo()
			->findActiveSessions()
			->order('start_date', 'DESC')
			->fetch();

		return $this->view(
			'DC\LoginAsUser:Session\Listing',
			'dcLoginAsUser_session_list',
			['sessions' => $sessions]
		);
	}

	public function actionSessionsEnd(ParameterBag $params): AbstractReply
	{
		$this->assertAdminPermission('dcLoginAsUser');

		$session = $this->assertSessionExists((int) $params->session_id);

		if (!$session->is_active)
		{
			// Not an error() - two administrators reaching for the same row is a race, not a
			// mistake, and the second one should be told the job is done rather than shown a
			// failure.
			return $this->message(\XF::phrase('dcLoginAsUser_session_is_no_longer_active'));
		}

		if ($this->isPost())
		{
			$this->getSessionRepo()->forceEndSession($session, \XF::visitor());

			return $this->redirect(
				$this->buildLink('login-as-user/sessions'),
				\XF::phrase('dcLoginAsUser_session_ended')
			);
		}

		return $this->view(
			'DC\LoginAsUser:Session\End',
			'dcLoginAsUser_session_end',
			['session' => $session]
		);
	}

	protected function assertSessionExists(int $sessionId): Session
	{
		/** @var Session $session */
		$session = $this->assertRecordExists(
			Session::class,
			$sessionId,
			['User', 'Target'],
			'dcLoginAsUser_requested_session_not_found'
		);

		return $session;
	}

	protected function getSessionRepo(): SessionRepository
	{
		return $this->repository(SessionRepository::class);
	}
}
