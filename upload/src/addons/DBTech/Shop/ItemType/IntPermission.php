<?php

namespace DBTech\Shop\ItemType;

/**
 * Class IntPermission
 *
 * @package DBTech\Shop\ItemType
 */
class IntPermission extends AbstractHandler
{
	/** @var array */
	protected $defaultAdminConfig = [
		'permissions' => []
	];
	
	
	/**
	 *
	 */
	public function addListeners()
	{
		$this->addListener('has_permission', function ($group, $permission, &$retval)
		{
			if (!isset($this->item->code['permissions'][$group][$permission]))
			{
				return;
			}
			
			if ($this->item->code['permissions'][$group][$permission] == 0)
			{
				return;
			}
			
			$retval = (int)$this->item->code['permissions'][$group][$permission];
		});
	}
	
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
			case 'user_config_view':
				/** @var \XF\Repository\Permission $permissionRepo */
				$permissionRepo = $this->repository('XF:Permission');
				
				$permissions = $permissionRepo->findPermissionsForList()
					->where('permission_type', 'integer')
					->fetch()
				;
				
				$params['permissionData'] = [
					'interfaceGroups' => $permissionRepo->findInterfaceGroupsForList()->fetch(),
					'permissionsGrouped' => $permissions->groupBy('interface_group_id')
				];
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
			'permissions' => 'array',
		]);
	}
}