<?php

namespace DBTech\Security\Admin\Controller;

use DBTech\Security\Admin\View;
use DBTech\Security\Entity\AdminStrike;
use DBTech\Security\Entity\BadBehavior;
use DBTech\Security\Entity\FingerprintLog;
use DBTech\Security\Entity\LoginStrike;
use DBTech\Security\Entity\WatcherLog;
use DBTech\Security\Searcher\CompromisedLog;
use XF\Admin\Controller\AbstractController;
use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Repository\ChangeLogRepository;

class LogController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		$this->assertAdminPermission('dbtechSecurity');
	}

	/**
	 * @return AbstractReply
	 */
	public function actionIndex(): AbstractReply
	{
		return $this->view(
			View\LogView::class,
			'dbtech_security_logs'
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionAdminStrikes(ParameterBag $params): AbstractReply
	{
		if ($params->admin_strike_id)
		{
			$entry = $this->assertAdminStrikeExists($params->admin_strike_id, [
				'User',
			], 'requested_log_entry_not_found');

			$viewParams = [
				'entry' => $entry,
			];
			return $this->view(
				View\Log\AdminStrikes\ViewView::class,
				'dbtech_security_log_admin_strikes_view',
				$viewParams
			);
		}

		$criteria = $this->filter('criteria', 'array');
		$order = $this->filter('order', 'str');
		$direction = $this->filter('direction', 'str');

		$page = $this->filterPage();
		$perPage = 20;

		/** @var \DBTech\Security\Searcher\AdminStrike $searcher */
		$searcher = $this->searcher(\DBTech\Security\Searcher\AdminStrike::class, $criteria);

		if ($order && !$direction)
		{
			$direction = $searcher->getRecommendedOrderDirection($order);
		}

		$searcher->setOrder($order, $direction);

		$finder = $searcher->getFinder();
		$finder->limitByPage($page, $perPage);

		$total = $finder->total();
		$entries = $finder->fetch();

		$viewParams = [
			'entries' => $entries,

			'total'   => $total,
			'page'    => $page,
			'perPage' => $perPage,

			'criteria'    => $searcher->getFilteredCriteria(),
			// 'filter' => $filter['text'],
			'sortOptions' => $searcher->getOrderOptions(),
			'order'       => $order,
			'direction'   => $direction,

		];
		return $this->view(
			View\Log\AdminStrikes\ListingView::class,
			'dbtech_security_log_admin_strikes_list',
			$viewParams
		);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionAdminStrikesSearch(): AbstractReply
	{
		$viewParams = $this->getAdminStrikeSearcherParams();

		return $this->view(
			View\Log\AdminStrikes\SearchView::class,
			'dbtech_security_log_admin_strikes_search',
			$viewParams
		);
	}

	/**
	 * @param array $extraParams
	 *
	 * @return array
	 */
	protected function getAdminStrikeSearcherParams(array $extraParams = []): array
	{
		/** @var \DBTech\Security\Searcher\AdminStrike $searcher */
		$searcher = $this->searcher(\DBTech\Security\Searcher\AdminStrike::class);

		$viewParams = [
			'criteria'   => $searcher->getFormCriteria(),
			'sortOrders' => $searcher->getOrderOptions(),
		];
		return $viewParams + $searcher->getFormData() + $extraParams;
	}

	/**
	 * @param int|null $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return AdminStrike
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertAdminStrikeExists(?int $id, array $with = [], ?string $phraseKey = null): AdminStrike
	{
		return $this->assertRecordExists(AdminStrike::class, $id, $with, $phraseKey);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionLoginStrikes(ParameterBag $params): AbstractReply
	{
		if ($params->login_strike_id)
		{
			$entry = $this->assertLoginStrikeExists($params->login_strike_id, [
			], 'requested_log_entry_not_found');

			$viewParams = [
				'entry' => $entry,
			];
			return $this->view(
				View\Log\LoginStrikes\ViewView::class,
				'dbtech_security_log_login_strikes_view',
				$viewParams
			);
		}

		$criteria = $this->filter('criteria', 'array');
		$order = $this->filter('order', 'str');
		$direction = $this->filter('direction', 'str');

		$page = $this->filterPage();
		$perPage = 20;

		/** @var \DBTech\Security\Searcher\LoginStrike $searcher */
		$searcher = $this->searcher(\DBTech\Security\Searcher\LoginStrike::class, $criteria);

		if ($order && !$direction)
		{
			$direction = $searcher->getRecommendedOrderDirection($order);
		}

		$searcher->setOrder($order, $direction);

		$finder = $searcher->getFinder();
		$finder->limitByPage($page, $perPage);

		$total = $finder->total();
		$entries = $finder->fetch();

		$viewParams = [
			'entries' => $entries,

			'total'   => $total,
			'page'    => $page,
			'perPage' => $perPage,

			'criteria'    => $searcher->getFilteredCriteria(),
			// 'filter' => $filter['text'],
			'sortOptions' => $searcher->getOrderOptions(),
			'order'       => $order,
			'direction'   => $direction,

		];
		return $this->view(
			View\Log\LoginStrikes\ListingView::class,
			'dbtech_security_log_login_strikes_list',
			$viewParams
		);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionLoginStrikesSearch(): AbstractReply
	{
		$viewParams = $this->getLoginStrikeSearcherParams();

		return $this->view(
			View\Log\LoginStrikes\SearchView::class,
			'dbtech_security_log_login_strikes_search',
			$viewParams
		);
	}

	/**
	 * @param array $extraParams
	 *
	 * @return array
	 */
	protected function getLoginStrikeSearcherParams(array $extraParams = []): array
	{
		/** @var \DBTech\Security\Searcher\LoginStrike $searcher */
		$searcher = $this->searcher(\DBTech\Security\Searcher\LoginStrike::class);

		$viewParams = [
			'criteria'   => $searcher->getFormCriteria(),
			'sortOrders' => $searcher->getOrderOptions(),
		];
		return $viewParams + $searcher->getFormData() + $extraParams;
	}

	/**
	 * @param int|null $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return LoginStrike
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertLoginStrikeExists(?int $id, array $with = [], ?string $phraseKey = null): LoginStrike
	{
		return $this->assertRecordExists(LoginStrike::class, $id, $with, $phraseKey);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionChanges(): AbstractReply
	{
		$page = $this->filterPage();
		$perPage = 20;

		$changeRepo = \XF::app()->repository(ChangeLogRepository::class);

		/** @noinspection PhpParamsInspection */
		$changeFinder = $changeRepo->findChangeLogsByContentType([
			'dbtech_security_option',
			'dbtech_security_adminperm',
			'dbtech_security_userperm',
			'dbtech_security_forumperm',
			'dbtech_security_groupperm',
			'dbtech_security_group',
			'user',
		])->limitByPage($page, $perPage);

		$changeFinder->whereOr(
			['content_type', '!=', 'user'],
			[
				['field', [
					'username',
					'password',
					'email',
					'user_group_id',
					'secondary_group_ids',
				]],
			]
		);

		if ($username = $this->filter('username', 'str'))
		{
			$limitUser = \XF::app()->em()->findOne(User::class, ['username' => $username]);
			if (!$limitUser)
			{
				return $this->error(\XF::phrase('requested_user_not_found'));
			}
		}
		else if ($userId = $this->filter('edit_user_id', 'uint'))
		{
			$limitUser = \XF::app()->em()->find(User::class, $userId);
			if (!$limitUser)
			{
				return $this->error(\XF::phrase('requested_user_not_found'));
			}
		}
		else
		{
			$limitUser = null;
		}

		$linkFilters = [];
		if ($limitUser)
		{
			$linkFilters['edit_user_id'] = $limitUser->user_id;
			$changeFinder->where('edit_user_id', $limitUser->user_id);
		}

		/** @var \XF\Mvc\Entity\AbstractCollection<\XF\Entity\ChangeLog> $changes */
		$changes = $changeFinder->fetch();
		$changeRepo->addDataToLogs($changes);

		$viewParams = [
			'changesGrouped' => $changeRepo->groupChangeLogs($changes),
			'totalChanges' => count($changes),
			'limitUser' => $limitUser,

			'page' => $page,
			'perPage' => $perPage,
			'total' => $changeFinder->total(),
			'linkFilters' => $linkFilters,
		];
		return $this->view(
			View\Log\Changes\ListingView::class,
			'dbtech_security_log_change_list',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \Exception
	 */
	public function actionCompromisedAccounts(ParameterBag $params): AbstractReply
	{
		$criteria = $this->filter('criteria', 'array');
		$order = $this->filter('order', 'str');
		$direction = $this->filter('direction', 'str');

		$page = $this->filterPage();
		$perPage = 20;

		/** @var CompromisedLog $searcher */
		$searcher = $this->searcher(CompromisedLog::class, $criteria);

		if ($order && !$direction)
		{
			$direction = $searcher->getRecommendedOrderDirection($order);
		}

		$searcher->setOrder($order, $direction);

		$finder = $searcher->getFinder();
		$finder->limitByPage($page, $perPage);

		$total = $finder->total();
		$entries = $finder->fetch();

		$viewParams = [
			'entries' => $entries,

			'total'   => $total,
			'page'    => $page,
			'perPage' => $perPage,

			'criteria'    => $searcher->getFilteredCriteria(),
			// 'filter' => $filter['text'],
			'sortOptions' => $searcher->getOrderOptions(),
			'order'       => $order,
			'direction'   => $direction,

		];
		return $this->view(
			View\Log\CompromisedAccounts\ListingView::class,
			'dbtech_security_log_compromised_account_list',
			$viewParams
		);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionCompromisedAccountsSearch(): AbstractReply
	{
		$viewParams = $this->getCompromisedAccountSearcherParams();

		return $this->view(
			View\Log\CompromisedAccounts\SearchView::class,
			'dbtech_security_log_compromised_account_search',
			$viewParams
		);
	}

	/**
	 * @param array $extraParams
	 *
	 * @return array
	 */
	protected function getCompromisedAccountSearcherParams(array $extraParams = []): array
	{
		/** @var CompromisedLog $searcher */
		$searcher = $this->searcher(CompromisedLog::class);

		$viewParams = [
			'criteria'   => $searcher->getFormCriteria(),
			'sortOrders' => $searcher->getOrderOptions(),
		];
		return $viewParams + $searcher->getFormData() + $extraParams;
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \Exception
	 */
	public function actionWatchers(ParameterBag $params): AbstractReply
	{
		if ($params->watcher_log_id)
		{
			$entry = $this->assertWatcherLogExists($params->watcher_log_id, [
				'Watcher',
				'User',
			], 'requested_log_entry_not_found');

			$viewParams = [
				'entry' => $entry,
			];
			return $this->view(
				View\Log\Watchers\ViewView::class,
				'dbtech_security_log_watcher_view',
				$viewParams
			);
		}

		$criteria = $this->filter('criteria', 'array');
		$order = $this->filter('order', 'str');
		$direction = $this->filter('direction', 'str');

		$page = $this->filterPage();
		$perPage = 20;

		/** @var \DBTech\Security\Searcher\WatcherLog $searcher */
		$searcher = $this->searcher(\DBTech\Security\Searcher\WatcherLog::class, $criteria);

		if ($order && !$direction)
		{
			$direction = $searcher->getRecommendedOrderDirection($order);
		}

		$searcher->setOrder($order, $direction);

		$finder = $searcher->getFinder();
		$finder->limitByPage($page, $perPage);

		$finder->with('Watcher', true);
		$finder->with(['User']);

		$total = $finder->total();
		$entries = $finder->fetch();

		$viewParams = [
			'entries' => $entries,

			'total'   => $total,
			'page'    => $page,
			'perPage' => $perPage,

			'criteria'    => $searcher->getFilteredCriteria(),
			// 'filter' => $filter['text'],
			'sortOptions' => $searcher->getOrderOptions(),
			'order'       => $order,
			'direction'   => $direction,

		];
		return $this->view(
			View\Log\Watchers\ListingView::class,
			'dbtech_security_log_watcher_list',
			$viewParams
		);
	}

	/**
	 * @return AbstractReply
	 * @throws \Exception
	 */
	public function actionWatchersSearch(): AbstractReply
	{
		$viewParams = $this->getWatcherSearcherParams();

		return $this->view(
			View\Log\Watchers\SearchView::class,
			'dbtech_security_log_watcher_search',
			$viewParams
		);
	}

	/**
	 * @param array $extraParams
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function getWatcherSearcherParams(array $extraParams = []): array
	{
		/** @var \DBTech\Security\Searcher\WatcherLog $searcher */
		$searcher = $this->searcher(\DBTech\Security\Searcher\WatcherLog::class);

		$viewParams = [
			'criteria'   => $searcher->getFormCriteria(),
			'sortOrders' => $searcher->getOrderOptions(),
		];
		return $viewParams + $searcher->getFormData() + $extraParams;
	}

	/**
	 * @param int|null $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return WatcherLog
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertWatcherLogExists(?int $id, array $with = [], ?string $phraseKey = null): WatcherLog
	{
		return $this->assertRecordExists(WatcherLog::class, $id, $with, $phraseKey);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \Exception
	 */
	public function actionFingerprints(ParameterBag $params): AbstractReply
	{
		if ($params->fingerprint_log_id)
		{
			$entry = $this->assertFingerprintLogExists($params->fingerprint_log_id, [
				'User',
			], 'requested_log_entry_not_found');

			$viewParams = [
				'entry' => $entry,
			];
			return $this->view(
				View\Log\Fingerprints\ViewView::class,
				'dbtech_security_log_fingerprint_view',
				$viewParams
			);
		}

		$criteria = $this->filter('criteria', 'array');
		$order = $this->filter('order', 'str');
		$direction = $this->filter('direction', 'str');

		$page = $this->filterPage();
		$perPage = 20;

		/** @var \DBTech\Security\Searcher\FingerprintLog $searcher */
		$searcher = $this->searcher(\DBTech\Security\Searcher\FingerprintLog::class, $criteria);

		if ($order && !$direction)
		{
			$direction = $searcher->getRecommendedOrderDirection($order);
		}

		$searcher->setOrder($order, $direction);

		$finder = $searcher->getFinder();
		$finder->limitByPage($page, $perPage);

		$finder->with('User');

		$total = $finder->total();
		$entries = $finder->fetch();

		$viewParams = [
			'entries' => $entries,

			'total'   => $total,
			'page'    => $page,
			'perPage' => $perPage,

			'criteria'    => $searcher->getFilteredCriteria(),
			// 'filter' => $filter['text'],
			'sortOptions' => $searcher->getOrderOptions(),
			'order'       => $order,
			'direction'   => $direction,

		];
		return $this->view(
			View\Log\Fingerprints\ListingView::class,
			'dbtech_security_log_fingerprint_list',
			$viewParams
		);
	}

	/**
	 * @return AbstractReply
	 * @throws \Exception
	 */
	public function actionFingerprintsSearch(): AbstractReply
	{
		$viewParams = $this->getFingerprintSearcherParams();

		return $this->view(
			View\Log\Fingerprints\SearchView::class,
			'dbtech_security_log_fingerprint_search',
			$viewParams
		);
	}

	/**
	 * @param array $extraParams
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function getFingerprintSearcherParams(array $extraParams = []): array
	{
		/** @var \DBTech\Security\Searcher\FingerprintLog $searcher */
		$searcher = $this->searcher(\DBTech\Security\Searcher\FingerprintLog::class);

		$viewParams = [
			'criteria'   => $searcher->getFormCriteria(),
			'sortOrders' => $searcher->getOrderOptions(),
		];
		return $viewParams + $searcher->getFormData() + $extraParams;
	}

	/**
	 * @param int|null $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return FingerprintLog
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertFingerprintLogExists(?int $id, array $with = [], ?string $phraseKey = null): FingerprintLog
	{
		return $this->assertRecordExists(FingerprintLog::class, $id, $with, $phraseKey);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \Exception
	 */
	public function actionBadBehavior(ParameterBag $params): AbstractReply
	{
		if ($params->id)
		{
			$entry = $this->assertBadBehaviorExists($params->id, [
			], 'requested_log_entry_not_found');

			$viewParams = [
				'entry' => $entry,
			];
			return $this->view(
				View\Log\BadBehavior\ViewView::class,
				'dbtech_security_log_bad_behavior_view',
				$viewParams
			);
		}

		$criteria = $this->filter('criteria', 'array');
		$order = $this->filter('order', 'str');
		$direction = $this->filter('direction', 'str');

		$page = $this->filterPage();
		$perPage = 20;

		/** @var \DBTech\Security\Searcher\BadBehavior $searcher */
		$searcher = $this->searcher(\DBTech\Security\Searcher\BadBehavior::class, $criteria);

		if ($order && !$direction)
		{
			$direction = $searcher->getRecommendedOrderDirection($order);
		}

		$searcher->setOrder($order, $direction);

		$finder = $searcher->getFinder();
		$finder->limitByPage($page, $perPage);

		$total = $finder->total();
		$entries = $finder->fetch();

		$viewParams = [
			'entries' => $entries,

			'total'   => $total,
			'page'    => $page,
			'perPage' => $perPage,

			'criteria'    => $searcher->getFilteredCriteria(),
			// 'filter' => $filter['text'],
			'sortOptions' => $searcher->getOrderOptions(),
			'order'       => $order,
			'direction'   => $direction,

		];
		return $this->view(
			View\Log\BadBehavior\ListingView::class,
			'dbtech_security_log_bad_behavior_list',
			$viewParams
		);
	}

	/**
	 * @return AbstractReply
	 * @throws \Exception
	 */
	public function actionBadBehaviorSearch(): AbstractReply
	{
		$viewParams = $this->getBadBehaviorSearcherParams();

		return $this->view(
			View\Log\BadBehavior\SearchView::class,
			'dbtech_security_log_bad_behavior_search',
			$viewParams
		);
	}

	/**
	 * @param array $extraParams
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function getBadBehaviorSearcherParams(array $extraParams = []): array
	{
		/** @var \DBTech\Security\Searcher\BadBehavior $searcher */
		$searcher = $this->searcher(\DBTech\Security\Searcher\BadBehavior::class);

		$viewParams = [
			'criteria'   => $searcher->getFormCriteria(),
			'sortOrders' => $searcher->getOrderOptions(),
		];
		return $viewParams + $searcher->getFormData() + $extraParams;
	}

	/**
	 * @param int|null $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return BadBehavior
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertBadBehaviorExists(?int $id, array $with = [], ?string $phraseKey = null): BadBehavior
	{
		return $this->assertRecordExists(BadBehavior::class, $id, $with, $phraseKey);
	}
}