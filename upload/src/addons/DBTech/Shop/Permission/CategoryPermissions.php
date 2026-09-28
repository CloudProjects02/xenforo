<?php

namespace DBTech\Shop\Permission;

use XF\Mvc\Entity\Entity;
use XF\Permission\TreeContentPermissions;

/**
 * Class CategoryPermissions
 *
 * @package DBTech\Shop\Permission
 */
class CategoryPermissions extends TreeContentPermissions
{
	/**
	 * @return string
	 */
	protected function getContentType(): string
	{
		return 'dbtech_shop_category';
	}
	
	/**
	 * @return \XF\Phrase
	 */
	public function getAnalysisTypeTitle(): \XF\Phrase
	{
		return \XF::phrase('dbtech_shop_category_permissions');
	}
	
	/**
	 * @param Entity $entity
	 *
	 * @return mixed|null
	 */
	public function getContentTitle(Entity $entity)
	{
		return $entity->title;
	}
	
	/**
	 * @param \XF\Entity\Permission $permission
	 *
	 * @return bool
	 */
	public function isValidPermission(\XF\Entity\Permission $permission): bool
	{
		return ($permission->permission_group_id == 'dbtech_shop' && !in_array($permission->permission_id, [
				'bank',
				'steal',
				'viewLottery',
				'trade'
			]));
	}
	
	/**
	 * @return \XF\Tree
	 */
	public function getContentTree(): \XF\Tree
	{
		/** @var \DBTech\Shop\Repository\Category $categoryRepo */
		$categoryRepo = $this->builder->em()->getRepository('DBTech\Shop:Category');
		return $categoryRepo->createCategoryTree($categoryRepo->findCategoryList()->fetch());
	}
	
	/**
	 * @param $contentId
	 * @param array $calculated
	 * @param array $childPerms
	 *
	 * @return array
	 */
	protected function getFinalPerms($contentId, array $calculated, array &$childPerms): array
	{
		if (!isset($calculated['dbtech_shop']))
		{
			$calculated['dbtech_shop'] = [];
		}

		$final = $this->builder->finalizePermissionValues($calculated['dbtech_shop']);

		if (empty($final['view']))
		{
			$childPerms['dbtech_shop']['view'] = 'deny';
		}

		return $final;
	}
	
	/**
	 * @param $contentId
	 * @param array $calculated
	 * @param array $childPerms
	 *
	 * @return array
	 */
	/**
	 * @param $contentId
	 * @param array $calculated
	 * @param array $childPerms
	 *
	 * @return array
	 */
	protected function getFinalAnalysisPerms($contentId, array $calculated, array &$childPerms): array
	{
		$final = $this->builder->finalizePermissionValues($calculated);

		if (empty($final['dbtech_shop']['view']))
		{
			$childPerms['dbtech_shop']['view'] = 'deny';
		}

		return $final;
	}
}