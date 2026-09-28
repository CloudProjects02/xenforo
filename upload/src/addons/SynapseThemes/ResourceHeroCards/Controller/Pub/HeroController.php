<?php

namespace SynapseThemes\ResourceHeroCards\Controller\Pub;

use XF\Pub\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class HeroController extends AbstractController
{
    public function actionIndex(ParameterBag $params)
    {
        // Fetch resource items from XenForo Resource Manager
        /** @var \XFRM\Repository\ResourceItem $resourceRepo */
        $resourceRepo = $this->repository('XFRM:ResourceItem');
        $finder = $resourceRepo->findResourcesForOverviewList()
            ->order('resource_date', 'DESC')
            ->limit(5);

        $resources = $finder->fetch();

        $viewParams = [
            'resources' => $resources
        ];

        // Return the view with the correct template path
        // The template is located at templates/public/xfdevs_hero.html
        return $this->view(
            'SynapseThemes\\ResourceHeroCards:Xfdevs\\Hero',
            'xfdevs_hero',
            $viewParams
        );
    }

    // Potentially other actions for more specific views or interactions
}