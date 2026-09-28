<?php

/*************************************************************************
 * Profile Views - XenConcept (c) 2020
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\ProfileViews;

use XF\Mvc\Entity\Entity;

class Listener
{

    protected static $_productId = 17;

    public static function appPubSetup(\XF\App $app)
    {
        $branding = $app->offsetExists('xenconcept_branding') ? $app->xenconcept_branding : [];

        $branding[] = self::$_productId;

        $app->xenconcept_branding = $branding;
    }

    public static function criteriaUser($rule, array $data, \XF\Entity\User $user, &$returnValue)
    {
        switch ($rule)
        {
            case 'view_count':
                if (isset($user->Profile->view_count) && $user->Profile->view_count >= $data['users'])
                {
                    $returnValue = true;
                }
                break;
        }
    }

    public static function userSearchOrders(\XF\Searcher\User $userSearcher, array &$sortOrders)
    {
        $sortOrders['xc_pv_profile_view_count'] = \XF::phrase('xc_profile_views_profile_view_count');
    }

    public static function userPrivacyEntityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        $structure->columns['allow_view_users_who_viewed_profile'] = [
            'type' => Entity::STR, 'default' => 'everyone',
            'allowedValues' => ['everyone', 'members', 'followed', 'none'],
            'verify' => 'verifyPrivacyChoice'
        ];
    }
}