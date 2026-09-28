<?php

namespace MMO\ResourceDownloaders\XFRM\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

class ResourceItem extends XFCP_ResourceItem
{
    /**
     * @param ParameterBag $params
     * @return View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionDownloaders(ParameterBag $params): View
    {
        $resource = $this->assertViewableResource($params->resource_id);
        $this->assertCanonicalUrl($this->buildLink('resources/downloaders', $resource));

        /** @var \MMO\ResourceDownloaders\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        /** @var \MMO\ResourceDownloaders\XFRM\Repository\ResourceItem $downloadersRepo */
        $downloadersRepo = $this->repository(\XFRM\Repository\ResourceItem::class);
        $finder = $downloadersRepo->findResourceDownloaders($resource);

        if (!$visitor->canViewWhoDownloadedResource())
        {
            return $this->noPermission();
        }

        $total = $finder->total();
        $page = $this->filterPage();
        $perPage = $this->options()->mrdXfrmDownoloadersPerPage;

        $finder->limitByPage($page, $perPage);

        $this->assertValidPage($page, $perPage, $total, 'resources/downloaders', $resource);
        $this->assertCanonicalUrl($this->buildLink('resources/downloaders', $resource, ['page' => $page]));

        return $this->view('XFRM:ResourceItem\ViewDownloaders', 'mrd_xfrm_resource_view_downloaders', [
            'downloaders' => $finder->fetch(),
            'resource' => $resource,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total
        ]);
    }
}
 		    	 			 									    		 		   	 	  		 		 	    				 		 	  	 				  	 		 	  	 			 	 		    		 		    		   	 	 		   				 					 					 	
