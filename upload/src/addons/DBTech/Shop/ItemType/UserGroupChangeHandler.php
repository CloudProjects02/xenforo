<?php

namespace DBTech\Shop\ItemType;

use XF\Finder\UserGroupFinder;
use XF\PrintableException;
use XF\Repository\UserGroupRepository;
use XF\Service\User\TempChangeService;
use XF\Service\User\UserGroupChangeService;

class UserGroupChangeHandler extends AbstractHandler
{
	/** @var array */
	protected array $defaultAdminConfig = [
		'usergroupid' 	=> [],
		'addremove' 	=> 'add',
	];


	/**
	 * @param string $context
	 *
	 * @return array
	 */
	protected function getDefaultTemplateParams(string $context): array
	{
		$params = parent::getDefaultTemplateParams($context);

		switch ($context)
		{
			case 'admin_config':
				$groupRepo = \XF::app()->repository(UserGroupRepository::class);
				$params['userGroups'] = $groupRepo->getUserGroupTitlePairs();
				break;

			case 'user_config_view':
				$params['userGroups'] = \XF::app()->finder(UserGroupFinder::class)->where('user_group_id', $this->item->code['usergroupid']);
				break;
		}

		return $params;
	}

	/**
	 * @param array $config
	 *
	 * @return array
	 */
	public function filterAdminConfig(array $config = []): array
	{
		return \XF::app()->inputFilterer()->filterArray($config, [
			'usergroupid' => 'array-uint',
			'addremove' => 'str',
		]);
	}

	/**
	 * @throws PrintableException
	 */
	protected function activateAlways(): void
	{
		$adminConfig = $this->item->code;
		$purchase = $this->purchase;

		if (empty($adminConfig['usergroupid']))
		{
			return;
		}

		if ($adminConfig['addremove'] == 'add')
		{
			if ($purchase->isLifetime())
			{
				$userGroupChange = \XF::app()->service(UserGroupChangeService::class);
				$userGroupChange->addUserGroupChange(
					$purchase->user_id,
					'dbtechShop-' . $purchase->purchase_id . '-' . $purchase->item_id,
					$adminConfig['usergroupid']
				);
			}
			else
			{
				$changeService = \XF::app()->service(TempChangeService::class);
				$changeService->applyGroupChange(
					$purchase->User,
					'dbtechShop-' . $purchase->purchase_id,
					$adminConfig['usergroupid'],
					'dbtechShop-' . $purchase->purchase_id . '-' . $purchase->item_id,
					$purchase->expiry_date
				);
			}
		}
		else
		{
			$user = $purchase->User;
			$ids = $user->secondary_group_ids;

			$changed = false;
			foreach ($adminConfig['usergroupid'] AS $groupId)
			{
				$position = array_search($groupId, $ids);
				if ($position !== false)
				{
					unset($ids[$position]);
					$changed = true;
				}
			}

			if ($changed)
			{
				$user->secondary_group_ids = $ids;
				$user->save();
			}
		}
	}

	/**
	 * @param null $error
	 *
	 * @throws PrintableException
	 */
	protected function _deactivate(&$error = null): void
	{
		$adminConfig = $this->item->code;
		$purchase = $this->purchase;

		if (empty($adminConfig['usergroupid']))
		{
			return;
		}

		if ($adminConfig['addremove'] == 'add')
		{
			if ($purchase->isLifetime())
			{
				$userGroupChange = \XF::app()->service(UserGroupChangeService::class);
				$userGroupChange->removeUserGroupChange(
					$purchase->user_id,
					'dbtechShop-' . $purchase->purchase_id . '-' . $purchase->item_id
				);
			}
			else
			{
				$changeService = \XF::app()->service(TempChangeService::class);
				$changeService->expireUserChangeByKey($purchase->User, 'dbtechShop-' . $purchase->purchase_id);
			}
		}
		else
		{
			$user = $purchase->User;
			$ids = $user->secondary_group_ids;

			$changed = false;
			foreach ($adminConfig['usergroupid'] AS $groupId)
			{
				$position = in_array($groupId, $ids);
				if ($position === false)
				{
					$ids[] = $groupId;
					$changed = true;
				}
			}

			if ($changed)
			{
				$user->secondary_group_ids = $ids;
				$user->save();
			}
		}
	}
}