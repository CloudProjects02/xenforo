<?php

namespace MMO\ResourceDownloaders\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Downloader extends AbstractController
{
    /**
     * @param $action
     * @param ParameterBag $params
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function preDispatchController($action, ParameterBag $params)
    {
        /** @var \MMO\ResourceDownloaders\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        if (!$visitor->canViewWhoDownloadedResource())
        {
            throw $this->exception($this->noPermission());
        }
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Reroute
     */
    public function actionIndex(ParameterBag $params)
    {
        if ($params->user_id)
        {
            return $this->rerouteController('MMO\ResourceDownloaders:Downloader', 'Downloader', $params);
        }
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionDownloader(ParameterBag $params)
    {
        /** @var \XF\Entity\User $user */
        $user = $this->assertRecordExists('XF:User', $params->user_id);

        /** @var \MMO\ResourceDownloaders\XFRM\Repository\ResourceItem $downloadersRepo */
        $downloadersRepo = $this->repository(\XFRM\Repository\ResourceItem::class);
        $finder = $downloadersRepo->findResourcesDownloadByUser($user->user_id);

        $total = $finder->total();

        $page = $this->filterPage();
        $perPage = $this->options()->mrdXfrmDownoloadersPerPage;

        $this->assertValidPage($page, $perPage, $total, 'resources/resource-downloaded', $user);
        $this->assertCanonicalUrl($this->buildLink('resources/resource-downloaded', $user, ['page' => $page]));

        $resources = $finder->limitByPage($page, $perPage)->fetch();

        return $this->view('MMO\ResourceDownloaders:Downloader\View', 'mrd_downloader_view', [
            'user' => $user,
            'resources' => $resources,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total
        ]);
    }

    /**
     * @param array $activities
     * @return \XF\Phrase
     */
    public static function getActivityDetails(array $activities): \XF\Phrase
    {
        return \XF::phrase('xfrm_viewing_resources');
    }
}
 		    	 			 									    		 		   	 	  		 		 	    				 		 	  	 				  	 		 	  	 			 	 		    		 		    		   	 	 		   				 					 					 	
