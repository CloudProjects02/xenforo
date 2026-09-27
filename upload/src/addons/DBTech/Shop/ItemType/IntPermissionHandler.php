<?php

namespace DBTech\Shop\ItemType;

use XF\Repository\PermissionRepository;

class IntPermissionHandler extends AbstractHandler
{
	/** @var array */
	protected array $defaultAdminConfig = [
		'permissions' => [],
	];


	/**
	 *
	 */
	public function addListeners(): void
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

			$retval = (int) $this->item->code['permissions'][$group][$permission];
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
				$permissionRepo = \XF::app()->repository(PermissionRepository::class);

				$permissions = $permissionRepo->findPermissionsForList()
					->where('permission_type', 'integer')
					->fetch()
				;

				$params['permissionData'] = [
					'interfaceGroups' => $permissionRepo->findInterfaceGroupsForList()->fetch(),
					'permissionsGrouped' => $permissions->groupBy('interface_group_id'),
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
		return \XF::app()->inputFilterer()->filterArray($config, [
			'permissions' => 'array',
		]);
	}
}