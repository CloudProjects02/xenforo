<?php

namespace DBTech\Shop\ItemType;

/**
 * Class UserGroupChange
 *
 * @package DBTech\Shop\ItemType
 */
class UserGroupChange extends AbstractHandler
{
	/** @var array */
	protected $defaultAdminConfig = [
		'usergroupid' 	=> [],
		'addremove' 	=> 'add'
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
				/** @var \XF\Repository\UserGroup $groupRepo */
				$groupRepo = $this->repository('XF:UserGroup');
				$params['userGroups'] = $groupRepo->getUserGroupTitlePairs();
				break;
			
			case 'user_config_view':
				$params['userGroups'] = $this->finder('XF:UserGroup')->where('user_group_id', $this->item->code['usergroupid']);
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
		return $this->app()->inputFilterer()->filterArray($config, [
			'usergroupid' => 'array-uint',
			'addremove' => 'str'
		]);
	}
	
	/**
	 * @throws \XF\PrintableException
	 */
	protected function activateAlways()
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
				/** @var \XF\Service\User\UserGroupChange $userGroupChange */
				$userGroupChange = $this->service('XF:User\UserGroupChange');
				$userGroupChange->addUserGroupChange(
					$purchase->user_id,
					'dbtechShop-' . $purchase->purchase_id . '-' . $purchase->item_id,
					$adminConfig['usergroupid']
				);
			}
			else
			{
				/** @var \XF\Service\User\TempChange $changeService */
				$changeService = $this->service('XF:User\TempChange');
				$changeService->applyGroupChange(
					$purchase->User,
					'dbtechShop-' . $purchase->purchase_id,
					$adminConfig['usergroupid'],
					'dbtechShop-' . $purchase->item_id,
					$purchase->expiry_date
				);
			}
		}
		else
		{
			$user = $purchase->User;
			$ids = $user->secondary_group_ids;
			
			$changed = false;
			foreach ($adminConfig['usergroupid'] as $groupId)
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
	 * @throws \XF\PrintableException
	 */
	protected function _deactivate(&$error = null)
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
				/** @var \XF\Service\User\UserGroupChange $userGroupChange */
				$userGroupChange = $this->service('XF:User\UserGroupChange');
				$userGroupChange->removeUserGroupChange(
					$purchase->user_id,
					'dbtechShop-' . $purchase->purchase_id . '-' . $purchase->item_id
				);
			}
			else
			{
				/** @var \XF\Service\User\TempChange $changeService */
				$changeService = $this->service('XF:User\TempChange');
				$changeService->expireUserChangeByKey($purchase->User, 'dbtechShop-' . $purchase->purchase_id);
			}
		}
		else
		{
			$user = $purchase->User;
			$ids = $user->secondary_group_ids;
			
			$changed = false;
			foreach ($adminConfig['usergroupid'] as $groupId)
			{
				$position = array_search($groupId, $ids);
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