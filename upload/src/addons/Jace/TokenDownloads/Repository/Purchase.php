<?php

namespace Jace\TokenDownloads\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Purchase extends Repository
{
    /**
     * @param int $userId
     * @return Finder
     */
    public function findPurchasesForUser($userId)
    {
        return $this->finder('Jace\TokenDownloads:Purchase')
            ->where('user_id', $userId)
            ->setDefaultOrder('purchase_date', 'DESC');
    }

    /**
     * @param int $userId
     * @return Finder
     */
    public function findActivePurchasesForUser($userId)
    {
        return $this->finder('Jace\TokenDownloads:Purchase')
            ->where('user_id', $userId)
            ->where('tokens_remaining', '>', 0);
    }
    
    /**
     * Checks if a user can download a resource using tokens
     * 
     * @param int $userId
     * @param int $resourceId
     * @return bool
     */
    public function canUserDownloadResource($userId, $resourceId)
    {
        if (!$userId)
        {
            return false;
        }
        
        $purchases = $this->findActivePurchasesForUser($userId)->fetch();
        return $purchases->count() > 0;
    }
    
    /**
     * Uses a token to download a resource
     * 
     * @param int $userId
     * @param \XFRM\Entity\ResourceItem $resource
     * @param \XFRM\Entity\ResourceVersion $version
     * @return bool
     */
    public function useTokenForDownload($userId, \XFRM\Entity\ResourceItem $resource, \XFRM\Entity\ResourceVersion $version)
    {
        if (!$userId)
        {
            return false;
        }
        
        $purchases = $this->findActivePurchasesForUser($userId)->fetch();
        if (!$purchases->count())
        {
            return false;
        }
        
        // Use the first available purchase with tokens
        $purchase = $purchases->first();
        
        if (!$purchase->useToken())
        {
            return false;
        }
        
        $purchase->save();
        
        // Log the token usage
        $this->createLog($purchase, $resource, $version);
        
        return true;
    }
    
    /**
     * Creates a log entry for token usage
     * 
     * @param \Jace\TokenDownloads\Entity\Purchase $purchase
     * @param \XFRM\Entity\ResourceItem $resource
     * @param \XFRM\Entity\ResourceVersion $version
     * @return \Jace\TokenDownloads\Entity\Log
     */
    protected function createLog(\Jace\TokenDownloads\Entity\Purchase $purchase, \XFRM\Entity\ResourceItem $resource, \XFRM\Entity\ResourceVersion $version)
    {
        /** @var \Jace\TokenDownloads\Entity\Log $log */
        $log = $this->em->create('Jace\TokenDownloads:Log');
        $log->user_id = $purchase->user_id;
        $log->purchase_id = $purchase->purchase_id;
        $log->resource_id = $resource->resource_id;
        $log->resource_version_id = $version->resource_version_id;
        $log->save();
        
        return $log;
    }
} 