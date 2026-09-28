<?php

namespace MMO\ResourceDownloaders\XFRM\Repository;

use XF\Mvc\Entity\Finder;
use XFRM\Entity\ResourceVersion;
use XFRM\Finder\ResourceDownload;

class ResourceItem extends XFCP_ResourceItem
{
    /**
     * @param \XFRM\Entity\ResourceItem $resourceItem
     * @return Finder
     */
    public function findResourceDownloaders(\XFRM\Entity\ResourceItem $resourceItem): Finder
    {
        $resourceFinder = $this->finder(ResourceDownload::class);
        $resourceFinder->with('User', true)
            ->where('resource_id', $resourceItem->resource_id)
            ->setDefaultOrder('last_download_date', 'desc');

        return $resourceFinder;
    }

    /**
     * @param ResourceVersion $resourceItem
     * @return Finder
     */
    public function getResourceVersionDownloaders(ResourceVersion $resourceItem): Finder
    {
        $resourceFinder = $this->finder(ResourceDownload::class);
        $resourceFinder->where('resource_id', $resourceItem->resource_id)
            ->where('resource_version_id', $resourceItem->resource_version_id)
            ->setDefaultOrder('last_download_date', 'desc');

        return $resourceFinder;
    }

    /**
     * @param $userId
     * @return Finder
     */
    public function findResourcesDownloadByUser($userId): Finder
    {
        $resourceFinder = $this->finder(ResourceDownload::class);
        $resourceFinder->with(['Resource', 'User'])
            ->where('user_id', $userId)
            ->setDefaultOrder('last_download_date', 'desc');

        return $resourceFinder;
    }
}
 		    	 			 									    		 		   	 	  		 		 	    				 		 	  	 				  	 		 	  	 			 	 		    		 		    		   	 	 		   				 					 					 	
