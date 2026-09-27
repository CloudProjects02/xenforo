<?php

declare(strict_types=1);

namespace DC\LoginAsUser\Pub\Controller;

use DC\LoginAsUser\Entity\Session as ImpersonationLog;
use DC\LoginAsUser\Impersonation;
use DC\LoginAsUser\Repository\SessionRepository;
use DC\LoginAsUser\Service\ImpersonationService;
use XF\Entity\User;
use XF\Finder\UserFinder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Pub\App;
use XF\Pub\Controller\AbstractController;

/**
 * One route row serves this whole controller. The prefix format ':int<user_id,username>/' compiles
 * to an OPTIONAL regex group, so a first segment that is not a user id simply falls through to the
 * action: 'login-as/quick' reaches actionQuick, 'login-as/username.123/confirm' reaches
 * actionConfirm with user_id bound.
 */
class LoginAsUserController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		App::$allowPageCache = false;
	}

	public function actionIndex(ParameterBag $params): AbstractReply
	{
		return $this->redirect($this->buildLink('login-as/quick'));
	}

	/**
	 * The staff-bar quick switch. GET renders the overlay; POST resolves the typed name and hands
	 * back the confirm View, which XF turns into a child overlay.
	 */
	public function actionQuick(ParameterBag $params): AbstractReply
	{
		$this->assertCanLoginAsUsers();
		$this->assertPasswordConfirmed();

		$visitor = \XF::visitor();

		if ($this->isPost())
		{
			$username = $this->filter('username', 'str');

			/** @var User|null $user */
			$user = $this->em()->findOne(UserFinder::class, ['username' => $username], ['Admin']);

			if (!$user)
			{
				return $this->error(\XF::phrase('requested_user_not_found'));
			}

			return $this->confirmView($user);
		}

		/** @var SessionRepository $sessionRepo */
		$sessionRepo = $this->repository(SessionRepository::class);
		$recentTargets = $sessionRepo->getRecentTargetsForActor((int) $visitor->user_id);

		$recentUsers = [];

		if ($recentTargets)
		{
			$recentUsers = $this->em()->findByIds(User::class, array_keys($recentTargets), ['Admin']);
		}

		$viewParams = [
			'username' => '',
			'recentTargets' => $recentTargets,
			'recentUsers' => $recentUsers,
		];

		return $this->view(
			'DC\LoginAsUser:Quick',
			'dcLoginAsUser_quick',
			$viewParams
		);
	}

	public function actionConfirm(ParameterBag $params): AbstractReply
	{
		$this->assertCanLoginAsUsers();
		$this->assertPasswordConfirmed();

		$user = $this->assertTargetExists($params);

		return $this->confirmView($user);
	}

	public function actionStart(ParameterBag $params): AbstractReply
	{
		$this->assertPostOnly();
		$this->assertCanLoginAsUsers();

		$user = $this->assertTargetExists($params);

		if (!$user->canLoginAs($error))
		{
			return $this->noPermission($error);
		}

		// Redirect explicitly to the confirm view: this action is POST-only, so letting the password
		// form default to "the current URL" would bounce the staff member to a GET of /start and a
		// bare "invalid request" once they had typed their password correctly.
		$this->assertPasswordConfirmed($this->buildLink('login-as/confirm', $user));

		/** @var ImpersonationService $service */
		$service = $this->service(ImpersonationService::class);
		$service->start(
			\XF::visitor(),
			$user,
			$this->filter('reason', 'str'),
			ImpersonationService::SOURCE_PUBLIC
		);

		return $this->redirect(
			$this->buildLink('index'),
			\XF::phrase('dcLoginAsUser_you_are_now_logged_in_as_x', ['name' => $user->username])
		);
	}

	/**
	 * Returning is gated ONLY on a marker existing. The permission is deliberately not re-checked:
	 * if it is revoked while somebody is mid-impersonation they must still be able to get out.
	 */
	public function actionStop(ParameterBag $params): AbstractReply
	{
		$this->assertPostOnly();

		if (!Impersonation::isActive())
		{
			return $this->redirect($this->buildLink('index'), '');
		}

		/** @var ImpersonationService $service */
		$service = $this->service(ImpersonationService::class);
		$targetName = '';

		if ($service->end(ImpersonationLog::END_MANUAL))
		{
			$targetName = $service->getEndedTargetUsername();
		}

		return $this->redirect(
			$this->buildLink('index'),
			\XF::phrase('dcLoginAsUser_you_are_no_longer_logged_in_as_x', ['name' => $targetName])
		);
	}

	protected function confirmView(User $user): AbstractReply
	{
		if (!$user->canLoginAs($error))
		{
			return $this->noPermission($error);
		}

		$viewParams = [
			'user' => $user,
			'reason' => $this->filter('reason', 'str'),
		];

		return $this->view(
			'DC\LoginAsUser:Confirm',
			'dcLoginAsUser_confirm',
			$viewParams
		);
	}

	protected function assertTargetExists(ParameterBag $params): User
	{
		// Loaded with Admin so the is_super_admin getter can answer without a second query.
		/** @var User $user */
		$user = $this->assertRecordExists(
			User::class,
			$params->user_id,
			['Admin'],
			'requested_user_not_found'
		);

		return $user;
	}

	/**
	 * Re-verify the staff member's OWN password before they take over an account.
	 *
	 * This is what makes a stolen staff session insufficient on its own: the attacker would also
	 * need the password. XF renders login_password_confirm for both the public and admin apps and
	 * posts it to login/password-confirm, which stamps passwordConfirm on the session this app owns,
	 * so nothing here has to be reimplemented.
	 */
	protected function assertPasswordConfirmed(?string $redirect = null): void
	{
		if (!Impersonation::requiresPasswordConfirmation())
		{
			return;
		}

		$this->assertPasswordVerified(Impersonation::PASSWORD_CONFIRM_WINDOW, $redirect);
	}

	protected function assertCanLoginAsUsers(): void
	{
		if (!\XF::visitor()->canLoginAsUsers())
		{
			throw $this->exception($this->noPermission());
		}
	}

	/**
	 * The same assert chain XF\Pub\Controller\LogoutController neuters, and for the same reason.
	 *
	 * actionStop has to be reachable no matter what state the impersonated account is in. If the
	 * target is banned, disabled, policy-locked or TFA-blocked, the ordinary chain would throw
	 * before the action ran - and the staff member would be trapped wearing an identity they cannot
	 * shed. Every action on this controller that is not stop() is already gated behind the
	 * dcLoginAsUser permission, so nothing is opened up by this.
	 */
	public function assertViewingPermissions($action)
	{
	}

	public function assertNotRejected($action)
	{
	}

	public function assertNotDisabled($action)
	{
	}

	public function assertBoardActive($action)
	{
	}

	public function assertTfaRequirement($action)
	{
	}

	public function assertPolicyAcceptance($action)
	{
	}

	public function assertNotSecurityLocked($action)
	{
	}
}
