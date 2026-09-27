<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Entity\User;
use XF\Entity\UserUpgrade;
use XF\Entity\UserUpgradeActive;
use XF\Finder\UserUpgradeActiveFinder;
use XF\Finder\UserUpgradeExpiredFinder;
use XF\Finder\UserUpgradeFinder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Repository\PaymentRepository;
use XF\Repository\UserGroupRepository;
use XF\Repository\UserUpgradeRepository;
use XF\Service\User\DowngradeService;
use XF\Service\User\UpgradeService;

class UserUpgradeController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('userUpgrade');
	}

	public function actionIndex()
	{
		$upgradeRepo = $this->getUserUpgradeRepo();
		$upgrades = $upgradeRepo->findUserUpgradesForList();

		$activeFinder = $upgradeRepo->findActiveUserUpgradesForList();
		$activeUpgrades = $activeFinder->fetch(5);

		$expiredFinder = $upgradeRepo->findExpiredUserUpgradesForList();
		$expiredUpgrades = $expiredFinder->fetch(5);

		$viewParams = [
			'upgrades' => $upgrades->fetch(),
			'activeUpgrades' => $activeUpgrades,
			'totalActiveUpgrades' => $activeFinder->total(),
			'expiredUpgrades' => $expiredUpgrades,
			'totalExpiredUpgrades' => $expiredFinder->total(),
		];
		return $this->view('XF:UserUpgrade\Listing', 'user_upgrade_list', $viewParams);
	}

	public function upgradeAddEdit(UserUpgrade $upgrade)
	{
		$paymentRepo = $this->repository(PaymentRepository::class);
		$paymentProfiles = $paymentRepo->findPaymentProfilesForList()->fetch();

		$upgradeRepo = $this->repository(UserUpgradeRepository::class);
		$upgrades = $upgradeRepo->getUpgradeTitlePairs();
		unset($upgrades[$upgrade->user_upgrade_id]);

		$viewParams = [
			'upgrade' => $upgrade,
			'upgrades' => $upgrades,
			'profiles' => $paymentProfiles,
			'totalProfiles' => $paymentProfiles->count(),
			'userGroups' => $this->repository(UserGroupRepository::class)->getUserGroupTitlePairs(),
		];
		return $this->view('XF:UserUpgrade\Edit', 'user_upgrade_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$upgrade = $this->assertUpgradeExists($params->user_upgrade_id);
		return $this->upgradeAddEdit($upgrade);
	}

	public function actionAdd()
	{
		$paymentRepo = $this->repository(PaymentRepository::class);
		if (!$paymentRepo->findPaymentProfilesForList()->total())
		{
			throw $this->exception($this->error(\XF::phrase('please_create_at_least_one_payment_profile_before_continuing')));
		}

		/** @var UserUpgrade $upgrade */
		$upgrade = $this->em()->create(UserUpgrade::class);
		return $this->upgradeAddEdit($upgrade);
	}

	protected function upgradeSaveProcess(UserUpgrade $upgrade)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'title' => 'str',
			'description' => 'str',
			'display_order' => 'uint',
			'extra_group_ids' => 'array-uint',
			'recurring' => 'bool',
			'cost_amount' => 'unum',
			'cost_currency' => 'str',
			'length_amount' => 'uint',
			'length_unit' => 'string',
			'payment_profile_ids' => 'array-uint',
			'disabled_upgrade_ids' => 'array-uint',
			'can_purchase' => 'bool',
		]);
		$form->basicEntitySave($upgrade, $input);

		$form->setup(function () use ($upgrade)
		{
			if ($this->filter('length_type', 'str') == 'permanent')
			{
				$upgrade->length_amount = 0;
				$upgrade->length_unit = '';
			}
		});

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params->user_upgrade_id)
		{
			$upgrade = $this->assertUpgradeExists($params->user_upgrade_id);
		}
		else
		{
			$upgrade = $this->em()->create(UserUpgrade::class);
		}
		$this->upgradeSaveProcess($upgrade)->run();

		return $this->redirect($this->buildLink('user-upgrades') . $this->buildLinkHash($upgrade->user_upgrade_id));
	}

	public function actionDelete(ParameterBag $params)
	{
		$upgrade = $this->assertUpgradeExists($params->user_upgrade_id);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$upgrade,
			$this->buildLink('user-upgrades/delete', $upgrade),
			$this->buildLink('user-upgrades/edit', $upgrade),
			$this->buildLink('user-upgrades'),
			$upgrade->title,
			null,
			[
				'deletionImportantPhrase' => 'if_any_users_have_active_upgrades_recommend_disable',
			]
		);
	}

	public function actionToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(UserUpgradeFinder::class, 'can_purchase');
	}

	public function actionManual(ParameterBag $params)
	{
		$upgrade = $this->assertUpgradeExists($params->user_upgrade_id);

		if ($this->isPost())
		{
			$username = $this->filter('username', 'str');
			$user = $this->em()->findOne(User::class, ['username' => $username]);
			if (!$user)
			{
				return $this->error(\XF::phrase('requested_user_not_found'));
			}

			$endDate = $this->filter('end_type', 'str') == 'date'
				? $this->filter('end_date', 'datetime')
				: 0;

			$upgradeService = $this->service(UpgradeService::class, $upgrade, $user);
			$upgradeService->setEndDate($endDate);
			$upgradeService->ignoreUnpurchasable(true);
			$upgradeService->upgrade();

			return $this->redirect($this->buildLink('user-upgrades'));
		}
		else
		{
			if ($upgrade->length_unit)
			{
				$endDate = strtotime('+' . $upgrade->length_amount . ' ' . $upgrade->length_unit);
			}
			else
			{
				$endDate = false;
			}

			$viewParams = [
				'endDate' => $endDate,
				'upgrade' => $upgrade,
			];
			return $this->view('XF:UserUpgrade\Manual', 'user_upgrade_manual', $viewParams);
		}
	}

	protected function setupUserUpgradeActiveFilterer(string $type = 'active'): \XF\Filterer\UserUpgrade
	{
		$setupData = [
			'finderType' => $type === 'active' ? UserUpgradeActiveFinder::class : UserUpgradeExpiredFinder::class,
			'defaultOrder' => $type === 'active' ? 'start_date' : 'end_date',
		];

		$filterer = $this->app->filterer(\XF\Filterer\UserUpgrade::class, $setupData);
		$filterer->addFilters($this->request, $this->filter('_skipFilter', 'str'));

		return $filterer;
	}

	public function actionActive(ParameterBag $params)
	{
		// Redirect old-style URLs with user_upgrade_id in path to query parameter format
		if ($params->user_upgrade_id)
		{
			return $this->redirect($this->buildLink('user-upgrades/active', null, ['user_upgrade_id' => $params->user_upgrade_id]));
		}

		$page = $this->filterPage();
		$perPage = 20;

		$filterer = $this->setupUserUpgradeActiveFilterer('active');
		$finder = $filterer->apply()->limitByPage($page, $perPage);

		$linkParams = $filterer->getLinkParams();
		$totalActive = $finder->total();

		$this->assertValidPage($page, $perPage, $totalActive, 'user-upgrades/active');

		if ($this->isPost())
		{
			// Redirect to GET
			return $this->redirect($this->buildLink('user-upgrades/active', null, $linkParams));
		}

		$viewParams = [
			'page' => $page,
			'perPage' => $perPage,
			'linkParams' => $linkParams,
			'filterDisplay' => $filterer->getDisplayValues(),
			'totalActive' => $totalActive,
			'activeUpgrades' => $finder->fetch(),
		];
		return $this->view('XF:UserUpgrade\Active', 'user_upgrade_active_list', $viewParams);
	}

	public function actionExpired(ParameterBag $params)
	{
		// Redirect old-style URLs with user_upgrade_id in path to query parameter format
		if ($params->user_upgrade_id)
		{
			return $this->redirect($this->buildLink('user-upgrades/expired', null, ['user_upgrade_id' => $params->user_upgrade_id]));
		}

		$page = $this->filterPage();
		$perPage = 20;

		$filterer = $this->setupUserUpgradeActiveFilterer('expired');
		$finder = $filterer->apply()->limitByPage($page, $perPage);

		$linkParams = $filterer->getLinkParams();
		$totalExpired = $finder->total();

		$this->assertValidPage($page, $perPage, $totalExpired, 'user-upgrades/expired');

		if ($this->isPost())
		{
			// Redirect to GET
			return $this->redirect($this->buildLink('user-upgrades/expired', null, $linkParams));
		}

		$viewParams = [
			'page' => $page,
			'perPage' => $perPage,
			'linkParams' => $linkParams,
			'filterDisplay' => $filterer->getDisplayValues(),
			'totalExpired' => $totalExpired,
			'expiredUpgrades' => $finder->fetch(),
		];
		return $this->view('XF:UserUpgrade\Expired', 'user_upgrade_expired_list', $viewParams);
	}

	public function actionActiveFilter(): AbstractReply
	{
		return $this->upgradeFilterView('active');
	}

	public function actionExpiredFilter(): AbstractReply
	{
		return $this->upgradeFilterView('expired');
	}

	protected function upgradeFilterView(string $type): AbstractReply
	{
		$filterer = $this->setupUserUpgradeActiveFilterer($type);

		$upgradeRepo = $this->getUserUpgradeRepo();
		$upgrades = $upgradeRepo->findUserUpgradesForList()->fetch();

		$paymentRepo = $this->repository(PaymentRepository::class);
		$paymentProfiles = $paymentRepo->findPaymentProfilesForList()->fetch();

		$viewParams = [
			'upgrades' => $upgrades,
			'paymentProfiles' => $paymentProfiles,
			'conditions' => $filterer->getFiltersForForm(),
			'datePresets' => \XF::language()->getDatePresets(),
			'type' => $type,
		];
		return $this->view('XF:UserUpgrade\Filter', 'user_upgrade_filter', $viewParams);
	}

	public function actionEditActive()
	{
		$activeUpgrade = $this->assertRecordExists(
			UserUpgradeActive::class,
			$this->filter('user_upgrade_record_id', 'uint'),
			['Upgrade', 'User']
		);

		if ($this->isPost())
		{
			$upgradeService = $this->service(UpgradeService::class, $activeUpgrade->Upgrade, $activeUpgrade->User);
			$upgradeService->ignoreUnpurchasable(true);
			$endType = $this->filter('end_type', 'str');
			if ($endType == 'permanent')
			{
				$upgradeService->setEndDate(0);
			}
			else
			{
				$upgradeService->setEndDate($this->filter('end_date', 'datetime'));
			}
			$upgradeService->upgrade();

			return $this->redirect($this->buildLink('user-upgrades/active'));
		}
		else
		{
			$viewParams = [
				'activeUpgrade' => $activeUpgrade,
			];
			return $this->view('XF:UserUpgrade\EditActive', 'user_upgrade_active_edit', $viewParams);
		}
	}

	public function actionDowngrade()
	{
		$activeUpgrade = $this->assertRecordExists(
			UserUpgradeActive::class,
			$this->filter('user_upgrade_record_id', 'uint'),
			['Upgrade', 'User']
		);

		if ($this->isPost())
		{
			$downgradeService = $this->service(DowngradeService::class, $activeUpgrade->Upgrade, $activeUpgrade->User);
			$downgradeService->setSendAlert(false);
			$downgradeService->downgrade();

			return $this->redirect($this->buildLink('user-upgrades/active'));
		}
		else
		{
			$viewParams = [
				'activeUpgrade' => $activeUpgrade,
			];
			return $this->view('XF:UserUpgrade\Downgrade', 'user_upgrade_active_downgrade', $viewParams);
		}
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return UserUpgrade
	 */
	protected function assertUpgradeExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(UserUpgrade::class, $id, $with, $phraseKey);
	}

	/**
	 * @return UserUpgradeRepository
	 */
	protected function getUserUpgradeRepo()
	{
		return $this->repository(UserUpgradeRepository::class);
	}
}
