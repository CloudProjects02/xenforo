<?php

namespace SynapseThemes\CustomResourceViewPage;

class Listener
{
    public static function loadClassExtensions(\XF\Event $event)
    {
        $event->setParam('class_extensions', [
            'XFRM\Pub\Controller\ResourceItem' => [
                'SynapseThemes\CustomResourceViewPage\XFRM\Pub\Controller\ResourceItem'
            ]
        ]);
    }
} 