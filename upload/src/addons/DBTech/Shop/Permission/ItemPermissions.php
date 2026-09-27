<?php

namespace DBTech\Shop\Permission;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Repository\ItemRepository;
use XF\Entity\Permission;
use XF\Entity\PermissionCombination;
use XF\Mvc\Entity\AbstractCollection;
use XF\Permission\AnalysisIntermediate;
use XF\Permission\FlatContentPermissions;
use XF\Phrase;
use XF\Repository\PermissionEntryRepository;

class ItemPermissions extends FlatContentPermissions
{
	/**
	 * @return string
	 */
	protected function getContentType(): string
	{
		return 'dbtech_shop_item';
	}

	/**
	 * @return Phrase
	 */
	public function getAnalysisTypeTitle(): Phrase
	{
		return \XF::phrase('dbtech_shop_item_permissions');
	}

	/**
	 * @return AbstractCollection
	 */
	public function getContentList(): AbstractCollection
	{
		$entryRepo = $this->builder->em()->getRepository(ItemRepository::class);
		return $entryRepo->findEntriesForPermissionList()->fetch();
	}

	/**
	 * @param Permission $permission
	 *
	 * @return bool
	 */
	public function isValidPermission(Permission $permission): bool
	{
		return ($permission->permission_group_id == 'dbtech_shop' && in_array($permission->permission_id, [
			'view',
			'purchase',
			'react',
			'rate',
		]));
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

	/**
	 * @param $contentId
	 * @param array $userGroupIds
	 * @param int $userId
	 *
	 * @return array
	 */
	public function getApplicablePermissionSets($contentId, array $userGroupIds, $userId = 0): array
	{
		$entryRepo = $this->builder->em()->getRepository(PermissionEntryRepository::class);

		$entries = $entryRepo->getContentPermissionEntriesGrouped('dbtech_shop_category');

		$item = \XF::app()->em()->find(Item::class, $contentId);

		$userEntries = $entries['users'];
		$groupEntries = $entries['groups'];
		$systemEntries = $entries['system'];

		$sets = [];
		foreach ($userGroupIds AS $userGroupId)
		{
			if (isset($groupEntries[$item->category_id][$userGroupId]))
			{
				$sets["shop-category-group-$userGroupId"] = $groupEntries[$item->category_id][$userGroupId];
			}
			if (isset($this->groupEntries[$contentId][$userGroupId]))
			{
				$sets["group-$userGroupId"] = $this->groupEntries[$contentId][$userGroupId];
			}
		}

		if ($userId && isset($userEntries[$item->category_id][$userId]))
		{
			$sets["shop-category-user-$userId"] = $userEntries[$item->category_id][$userId];
		}
		if ($userId && isset($this->userEntries[$contentId][$userId]))
		{
			$sets["user-$userId"] = $this->userEntries[$contentId][$userId];
		}

		if (isset($systemEntries[$item->category_id]))
		{
			$sets['shop-category-system'] = $systemEntries[$item->category_id];
		}
		if (isset($this->systemEntries[$contentId]))
		{
			$sets['system'] = $this->systemEntries[$contentId];
		}

		return $sets;
	}

	/**
	 * @param PermissionCombination $combination
	 * @param $contentId
	 * @param array $basePerms
	 * @param array $baseIntermediates
	 *
	 * @return array
	 */
	public function analyzeCombination(
		PermissionCombination $combination,
		$contentId,
		array $basePerms,
		array $baseIntermediates
	): array
	{
		$groupIds = $combination->user_group_list;
		$userId = $combination->user_id;

		$intermediates = $baseIntermediates;
		$permissions = $basePerms;
		$dependChanges = [];

		$titles = $this->getAnalysisContentPairs();

		$permissions = $this->adjustBasePermissionAllows($permissions);

		$sets = $this->getApplicablePermissionSets($contentId, $groupIds, $userId);
		$permissions = $this->builder->calculatePermissions($sets, $this->permissionsGrouped, $permissions);

		$calculated = $this->builder->applyPermissionDependencies(
			$permissions,
			$this->permissionsGrouped,
			$dependChanges
		);
		$finalPerms = $this->getFinalAnalysisPerms($contentId, $calculated, $permissions);

		$thisIntermediates = $this->builder->collectIntermediates(
			$combination,
			$permissions,
			$sets,
			$contentId,
			$titles[$contentId]
		);
		$thisIntermediates = array_merge($thisIntermediates, $this->collectCategoryIntermediates(
			$combination,
			$permissions,
			$sets,
			$contentId,
			$titles[$contentId]
		));
		$intermediates = $this->builder->pushIntermediates($intermediates, $thisIntermediates);

		return $this->builder->getFinalAnalysis($finalPerms, $intermediates, $dependChanges);
	}

	protected function collectCategoryIntermediates(
		PermissionCombination $combination,
		array $groupedPermissions,
		array $sets,
		$contentId = null,
		$contentTitle = null
	): array
	{
		$groupIds = $combination->user_group_list;
		$userId = $combination->user_id;

		$intermediates = [];

		foreach ($groupedPermissions AS $permissionGroupId => $permissions)
		{
			foreach ($permissions AS $permissionId => $null)
			{
				$localIntermediates = [];

				if (isset($sets['shop-category-system'][$permissionGroupId][$permissionId]))
				{
					$localIntermediates[] = new AnalysisIntermediate(
						$sets['shop-category-system'][$permissionGroupId][$permissionId],
						'shop-category-system',
						null,
						$contentId,
						$contentTitle
					);
				}

				foreach ($groupIds AS $groupId)
				{
					if (isset($sets["shop-category-group-$groupId"][$permissionGroupId][$permissionId]))
					{
						$intermediateValue = $sets["shop-category-group-$groupId"][$permissionGroupId][$permissionId];
					}
					else
					{
						$permission = $this->permissionsGrouped[$permissionGroupId][$permissionId];
						$intermediateValue = $permission->permission_type == 'integer' ? 0 : 'unset';
					}

					$skipDefault = ($contentId && ($intermediateValue === 'unset' || $intermediateValue === 0));
					if (!$skipDefault)
					{
						$localIntermediates[] = new AnalysisIntermediate(
							$intermediateValue,
							'shop-category-group',
							$groupId,
							$contentId,
							$contentTitle
						);
					}
				}

				if ($userId && isset($sets["shop-category-user-$userId"][$permissionGroupId][$permissionId]))
				{
					$localIntermediates[] = new AnalysisIntermediate(
						$sets["shop-category-user-$userId"][$permissionGroupId][$permissionId],
						'shop-category-user',
						$userId,
						$contentId,
						$contentTitle
					);
				}

				$intermediates[$permissionGroupId][$permissionId] = $localIntermediates;
			}
		}

		return $intermediates;
	}
}