<?php

namespace SV\SignupAbuseBlocking\Repository;

use XF\Container;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use SV\SignupAbuseBlocking\Finder\AbstractAllowOrBanItem as ItemFinder;
use XF\Mvc\Entity\Structure as EntityStructure;
use XF\Mvc\Entity\Manager as EntityManager;
use SV\SignupAbuseBlocking\Entity\AbstractAllowOrBanItem as ItemEntity;
use XF\DataRegistry;
use function get_class;

/**
 * Class AbstractAllowOrBanItem
 *
 * @package SV\SignupAbuseBlocking\Repository
 */
abstract class AbstractAllowOrBanItem extends Repository
{
    abstract protected function getCacheKey(): string;

    public function findItems(): ItemFinder
    {
        return $this->getItemFinder()->setDefaultOrder($this->getPrimaryKey());
    }

    /**
     * @param string|int $id
     *
     * @return Entity|null
     */
    public function findItem($id): ?Entity
    {
        return $this->em()->find($this->getIdentifier(), $id);
    }

    /**
     * @param string|int      $primaryKeyValue
     * @param string          $reason
     * @param UserEntity|null $user
     * @return ItemEntity
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function addItem($primaryKeyValue, string $reason = '', ?UserEntity $user = null): ItemEntity
    {
        $user = $user ?: \XF::visitor();

        /** @var ItemEntity $item */
        $item = $this->em()->create($this->identifier);
        $item->bulkSet([
            $this->getPrimaryKey() => $primaryKeyValue,
            'reason' => $reason,
            'create_user_id' => $user->user_id
        ]);
        $item->save();

        $this->rebuildItemCacheLater();

        return $item;
    }

    public function rebuildItemCacheLater(): void
    {
        \XF::runOnce('svRebuildItemCache-'.get_class($this), function () {
            $this->rebuildItemCache();
        });
    }

    public function rebuildItemCache(): array
    {
        $cache = \array_column(
            $this->findItems()->fetchColumns($this->getPrimaryKey()),
            $this->getPrimaryKey()
        );

        $this->dataRegistry()->set($this->getCacheKey(), $cache);

        return $cache;
    }

    /**
     * @param string $value
     * @param array $items
     *
     * @return bool|string
     */
    abstract public function valuesMatches(string $value, array $items);

    /**
     * @param string $value
     * @return bool|string
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function valueMatchesFromCache(string $value)
    {
        $cacheKey = $this->getCacheKey();
        $app = $this->app();
        $c = $app->container();
        if (!$c->offsetExists($cacheKey))
        {
            $c[$cacheKey] = $app->fromRegistry($cacheKey, function (Container $c) {
                return $this->rebuildItemCache();
            });
        }

        $cache = $c[$cacheKey];
        if (!$cache)
        {
            return false;
        }

        return $this->valuesMatches($value, $cache);
    }

    public function triggerItemUsage(string $item)
    {
        $db = $this->db();
        $db->query("
            UPDATE {$this->getTable()}
            SET last_triggered_date = ?,
                trigger_count = trigger_count + 1
            WHERE {$this->getPrimaryKey()} = ?
        ", [\XF::$time, $item]);

        $em = $this->em();
        $itemEntity = $em->find($this->getIdentifier(), $item);
        if ($itemEntity)
        {
            $em->detachEntity($itemEntity);
        }
    }

    /**
     * @return Finder|ItemFinder
     */
    protected function getItemFinder(): ItemFinder
    {
        return $this->finder($this->identifier);
    }

    protected function em(): EntityManager
    {
        return $this->app()->em();
    }

    protected function getStructure(): EntityStructure
    {
        return $this->em()->getEntityStructure($this->identifier);
    }

    /**
     * @return string|int
     */
    protected function getPrimaryKey()
    {
        return $this->getStructure()->primaryKey;
    }

    protected function getTable(): string
    {
        return $this->getStructure()->table;
    }

    protected function getIdentifier(): string
    {
        return $this->identifier;
    }

    protected function dataRegistry(): DataRegistry
    {
        return $this->app()->registry();
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
