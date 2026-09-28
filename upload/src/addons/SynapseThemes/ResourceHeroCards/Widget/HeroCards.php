<?php

namespace SynapseThemes\ResourceHeroCards\Widget;

use XF\Widget\AbstractWidget;

class HeroCards extends AbstractWidget
{
    public function render()
    {
        // Fetch the latest 5 resources
        $resourceRepo = \XF::app()->repository('XFRM:ResourceItem');
        $finder = $resourceRepo->findResourcesForOverviewList()
            ->order('resource_date', 'DESC')
            ->limit(5);
        $resources = $finder->fetch();

        $viewParams = [
            'resources' => $resources
        ];

        return $this->renderer('xfdevs_hero', $viewParams);
    }

    public function getOptionsTemplate()
    {
        return null;
    }
} 