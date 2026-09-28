<?php

namespace Jace\PopularXFRM;

use XF\Mvc\Entity\AbstractCollection;

class Listener
{
    public static function memberControllerPreDispatch(\XF\Mvc\Controller $controller, $action, \XF\Mvc\ParameterBag $params, \XF\Mvc\Reply\AbstractReply &$reply)
    {
        if ($action === 'View' && $controller instanceof \XF\Pub\Controller\Member)
        {
            // We'll handle this in the template modification instead
            // This allows us to avoid overriding the entire controller action
        }
    }

    public static function memberTemplateData($templater, &$type, &$template, array &$params)
    {
        if ($template === 'member_view' && !empty($params['user']))
        {
            $user = $params['user'];
            $visitor = \XF::visitor();

            // Only fetch if user has resources and visitor can view them
            if ($user->xfrm_resource_count > 0 && $visitor->canViewResources())
            {
                $resourceRepo = \XF::repository('XFRM:ResourceItem');
                
                $finder = $resourceRepo->findResourcesByUser($user->user_id)
                    ->where('resource_state', 'visible')
                    ->order([
                        ['rating_weighted', 'DESC'],
                        ['download_count', 'DESC'],
                        ['last_update', 'DESC']
                    ])
                    ->limit(5);
                
                $popularResources = $finder->fetch()->filterViewable();
                
                $params['popularResources'] = $popularResources;
            }
        }
    }
}
