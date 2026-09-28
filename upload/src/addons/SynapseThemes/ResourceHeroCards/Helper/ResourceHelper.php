<?php

namespace SynapseThemes\ResourceHeroCards\Helper;

use XF\Mvc\Entity\Finder;
use XF\App;

class ResourceHelper
{
    /**
     * Fetches resource items for hero card displays.
     *
     * @param App $app The XenForo application object.
     * @param int $limit The maximum number of resources to fetch.
     * @param string $order The field to order by (e.g., 'resource_date').
     * @param string $direction The order direction ('ASC' or 'DESC').
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    public static function getResourceHeroItems(App $app, int $limit = 5, string $order = 'resource_date', string $direction = 'DESC')
    {
        /** @var \XF\Mvc\Entity\Finder $resourceFinder */
        // Assuming the entity for resources is 'XFRM:ResourceItem'. 
        // This might need to be adjusted if the Resource Manager uses a different entity name.
        $resourceFinder = $app->finder('XFRM:ResourceItem');

        $resources = $resourceFinder
            ->where('resource_state', 'visible') // Fetch only visible resources
            ->order($order, $direction)
            ->limit($limit)
            ->fetch();

        return $resources;
    }
}