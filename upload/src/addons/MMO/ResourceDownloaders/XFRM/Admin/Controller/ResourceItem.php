<?php

namespace MMO\ResourceDownloaders\XFRM\Admin\Controller;

use XF\Mvc\Entity\Finder;
use XFRM\Finder\ResourceDownload;

class ResourceItem extends XFCP_ResourceItem
{
    public function actionDownloads()
    {
        $page = $this->filterPage();
        $perPage = 20;

        /** @var Finder $finder **/
        $resourceDownloadFinder = $this->finder(ResourceDownload::class);
        $resourceDownloadFinder
            ->with('User')
            ->with('Resource')
            ->order('last_download_date', 'desc')
            ->limitByPage($page, $perPage);

        $filters = $this->getDownloadFilterInput();
        $this->applyDownloadFilters($resourceDownloadFinder, $filters);

        if ($this->isPost())
        {
            return $this->redirect($this->buildLink('resource-manager/downloads', null, $filters));
        }

        return $this->view('XFRM:ResourceItem\Downloads', 'mrd_xfrm_resource_view_download_list', [
            'resources' => $this->getResourcesList(),
            'downloads' => $resourceDownloadFinder->fetch(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $resourceDownloadFinder->total(),
            'linkFilters' => $filters,
        ]);
    }

    protected function getResourcesList(): array
    {
        $resources = [];
        $resourceFinder = $this->finder(\XFRM\Finder\ResourceItem::class);

        foreach ($resourceFinder as $resource)
        {
            $resources[$resource->get('resource_id')] = $resource->get('title');
        }

        return $resources;
    }

    protected function getDownloadFilterInput(): array
    {
        $filters = [];

        $input = $this->filter([
            'resource_id' => 'uint',
            'username' => 'str'
        ]);

        if ($input['username'])
        {
            $filters['username'] = $input['username'];
            $user = $this->em()->findOne('XF:User', ['username' => $filters['username']]);
            if ($user)
            {
                $filters['user_id'] = $user->user_id;
            }
        }

        if($input['resource_id'])
        {
            $filters['resource_id'] = $input['resource_id'];
        }

        return $filters;
    }

    /**
     * @param Finder $resourceDownloadFinder
     * @param array $filters
     */
    protected function applyDownloadFilters(Finder $resourceDownloadFinder, array $filters)
    {
        if (!empty($filters['user_id']))
        {
            $resourceDownloadFinder->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['resource_id']))
        {
            $resourceDownloadFinder->where('resource_id', $filters['resource_id']);
        }
    }
}
 		    	 			 									    		 		   	 	  		 		 	    				 		 	  	 				  	 		 	  	 			 	 		    		 		    		   	 	 		   				 					 					 	
