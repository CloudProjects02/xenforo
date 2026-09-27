<?php

namespace DBTech\Shop\Repository;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Finder\ItemFilterMapFinder;
use XF\Mvc\Entity\Repository;

class ItemFilterMapRepository extends Repository
{
	/**
	 * @param int $itemId
	 * @param array $filterIds
	 *
	 * @throws \InvalidArgumentException
	 */
	public function updateItemAssociations(int $itemId, array $filterIds): void
	{
		$db = $this->db();
		$db->beginTransaction();

		$db->delete('xf_dbtech_shop_item_filter_map', 'item_id = ?', $itemId);

		$map = [];

		foreach ($filterIds AS $filterId)
		{
			$map[] = [
				'item_id' => $itemId,
				'filter_id' => $filterId,
			];
		}

		if ($map)
		{
			$db->insertBulk('xf_dbtech_shop_item_filter_map', $map);
		}

		$this->rebuildItemAssociationCache([$itemId]);

		$db->commit();
	}

	/**
	 * @param array $itemIds
	 */
	public function rebuildItemAssociationCache(array $itemIds): void
	{
		if (!$itemIds)
		{
			return;
		}

		$newCache = [];

		$filterAssociations = \XF::app()->finder(ItemFilterMapFinder::class)
			->where('item_id', $itemIds);
		foreach ($filterAssociations->fetch() AS $filterMap)
		{
			$newCache[$filterMap->item_id][] = $filterMap->filter_id;
		}

		foreach ($itemIds AS $itemId)
		{
			if (!isset($newCache[$itemId]))
			{
				$newCache[$itemId] = [];
			}
		}

		$this->updateAssociationCache($newCache);
	}

	/**
	 * @param array $cache
	 */
	protected function updateAssociationCache(array $cache): void
	{
		$itemIds = array_keys($cache);
		$items = \XF::app()->em()->findByIds(Item::class, $itemIds);

		foreach ($items AS $item)
		{
			/** @var Item $item */
			$item->item_filters = $cache[$item->item_id];
			$item->saveIfChanged();
		}
	}
}