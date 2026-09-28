<?php

namespace AddonsLab\Core;

/**
 * Class AbstractBatchDataLoader
 * @package AddonsLab\Helper\ContainerService
 * Used in all cases when we would like to preload data from the database using a single query
 * and fetch the data later for each entry without hitting the database.
 * Preloading is optional and data is returned from the database if it does not exist in the cache
 */
abstract class AbstractBatchDataLoader
{
    /**
     * Should execute the query and load all items matching the IDs provided
     * @param array $itemIds
     * @return array An array with primary_key=>item mapping
     */
    abstract protected function _getItemsByIds(array $itemIds);

    /**
     * @var array Array of loaded pictures
     */
    protected $item_cache = [];

    /**
     * @var array Ids of pictures pending to be loaded from database
     */
    protected $preload_item_ids = [];

    protected $failed_item_ids = [];

    /**
     * @param array $itemIds
     * Add picture IDs to the queue to preload
     */
    public function preloadItemIds(array $itemIds)
    {
        // adding IDs in the queue instead of merging them into one
        // merging will be done with one single call when calling getItems method
        $this->preload_item_ids[] = $itemIds;
    }

    public function getItem($itemId)
    {
        $items = $this->getItems([$itemId]);
        if (isset($items[$itemId])) {
            return $items[$itemId];
        }
        return null;
    }

    /**
     * Fetch items from the database. Uses pre-loaded information if available.
     * The items returned are in the same order as the IDs provided
     * @param array $itemIds
     * @return array
     */
    public function getItems(array $itemIds)
    {
        $missingItemIds = [];

        foreach ($itemIds AS $itemId) {
            if (
                !isset($this->item_cache[$itemId]) && !in_array($itemId, $this->failed_item_ids, true)) {
                $missingItemIds[] = $itemId;
            }
        }

        if (!empty($missingItemIds)) {
            // some pictures are missing
            $this->preloadItemIds($missingItemIds);
            $this->_loadPendingItemsFromDb();
            foreach ($missingItemIds AS $itemId) {
                if (!isset($this->item_cache[$itemId])) {
                    $this->failed_item_ids[] = $itemId;
                }
            }
        }

        $foundItems = [];
        foreach ($itemIds AS $itemId) {
            if (isset($this->item_cache[$itemId])) {
                $foundItems[$itemId] = $this->item_cache[$itemId];
            }
        }

        return $foundItems;
    }


    public function _loadPendingItemsFromDb()
    {
        if (empty($this->preload_item_ids)) {
            return;
        }

        $itemIds = [];

        foreach ($this->preload_item_ids AS $preload_item_id) {
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $itemIds = array_merge($itemIds, $preload_item_id);
        }

        $itemIds = array_unique($itemIds);

        // use for PHP 5.6
        // $itemIds = array_merge(...$this->preload_item_ids);

        $itemIds = array_diff(
            $itemIds,
            array_keys($this->item_cache),
            $this->failed_item_ids
        );

        if (empty($itemIds)) {
            $this->preload_item_ids = [];
            return;
        }

        $items = $this->_getItemsByIds($itemIds);

        $this->item_cache += $items;

        $this->preload_item_ids = [];
    }
}
