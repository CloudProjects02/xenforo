<?php

namespace MMO\ResourceDownloaders\XFRM\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

class ResourceVersion extends XFCP_ResourceVersion
{
    /**
     * @param ParameterBag $params
     * @return View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionDownloaders(ParameterBag $params): View
    {
        $version = $this->assertViewableVersion($params->resource_version_id);
        $resource = $version->Resource;

        $this->assertCanonicalUrl($this->buildLink('resources/version/downloaders', $version));

        /** @var \MMO\ResourceDownloaders\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        /** @var \MMO\ResourceDownloaders\XFRM\Repository\ResourceItem $downloadersRepo */
        $downloadersRepo = $this->repository(\XFRM\Repository\ResourceItem::class);
        $finder = $downloadersRepo->getResourceVersionDownloaders($version);

        if (!$visitor->canViewWhoDownloadedResource())
        {
            return $this->noPermission();
        }

        $total = $finder->total();
        $page = $this->filterPage();
        $perPage = $this->options()->mrdXfrmDownoloadersPerPage;

        $this->assertValidPage($page, $perPage, $total, 'resources/version/downloaders', $version);
        $this->assertCanonicalUrl($this->buildLink('resources/version/downloaders', $version, ['page' => $page]));

        $downloaders = $finder->limitByPage($page, $perPage)->fetch();

        return $this->view('XFRM:ResourceVersion\DownloadersVersion', 'mrd_xfrm_resource_view_version_downloaders', [
            'downloaders' => $downloaders,
            'version' => $version,
            'resource' => $resource,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total
        ]);
    }
}
 		    	 			 									    		 		   	 	  		 		 	    				 		 	  	 				  	 		 	  	 			 	 		    		 		    		   	 	 		   				 					 					 	
