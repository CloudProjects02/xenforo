<?php

namespace DBTech\Shop\Repository;

use XF\Mvc\Entity\Repository;

/**
 * Class ItemFilterMap
 *
 * @package DBTech\Shop\Repository
 */
class ItemFilterMap extends Repository
{
	/**
	 * @param int $itemId
	 * @param array $filterIds
	 *
	 * @throws \InvalidArgumentException
	 */
	public function updateItemAssociations(int $itemId, array $filterIds)
	{
		$db = $this->db();
		$db->beginTransaction();

		$db->delete('xf_dbtech_shop_item_filter_map', 'item_id = ?', $itemId);

		$map = [];

		foreach ($filterIds AS $filterId)
		{
			$map[] = [
				'item_id' => $itemId,
				'filter_id' => $filterId
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
	public function rebuildItemAssociationCache(array $itemIds)
	{
		if (!$itemIds)
		{
			return;
		}

		$newCache = [];

		$filterAssociations = $this->finder('DBTech\Shop:ItemFilterMap')
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
	protected function updateAssociationCache(array $cache)
	{
		$itemIds = array_keys($cache);
		$items = $this->em->findByIds('DBTech\Shop:Item', $itemIds);

		foreach ($items AS $item)
		{
			/** @var \DBTech\Shop\Entity\Item $item */
			$item->item_filters = $cache[$item->item_id];
			$item->saveIfChanged();
		}
	}
}