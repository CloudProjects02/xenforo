<?php

namespace XC\RankingSystem;

use XF\Mvc\Controller\assertCanonicalUrl;

class Listener {

    public static function templaterSetup(\XF\Container $container, \XF\Template\Templater &$templater) {



        $class = \XF::extendClass('XC\RankingSystem\Template\TemplaterSetup');

        $templaterSetup = new $class();

        $templater->addFunction('xc_user_point_data', [$templaterSetup, 'userPointData']);

        $templater->addFunction('xc_user_unlock_badges', [$templaterSetup, 'userUnlockBadges']);
    }

    public static function templateMacroPreRenderXpMacrosUserInfo(
            \XF\Template\Templater $templater,
            &$type,
            &$template,
            &$name,
            array &$arguments,
            array &$globalVars
    ) {
        if (isset($GLOBALS['allow_to_progressbar'])) {

            $globalVars['allow_to_progressbar'] = $GLOBALS['allow_to_progressbar'];
        }
    }

    public static function postDispatchController(\XF\Mvc\Controller $controller, $action, \XF\Mvc\ParameterBag $params, \XF\Mvc\Reply\AbstractReply &$reply) {

        $visitor = \xf::visitor();

        if ($visitor->user_id) {


            if (!$visitor->first_visit && !$visitor->latest_visit) {

                $visitor->fastUpdate('first_visit', $visitor->last_activity);

                $visitor->fastUpdate('latest_visit', \xf::$time);

                if ((\xf::$time - $visitor->last_activity) > 86400) {

                    static::awardDailyVisit($visitor);
                }
            } elseif ((\xf::$time - $visitor->latest_visit) > 86400 && (\xf::$time - $visitor->latest_visit) < (86400 * 2)) {

                $visitor->fastUpdate('latest_visit', \xf::$time);

                static::awardDailyVisit($visitor);
            } elseif ((\xf::$time - $visitor->latest_visit) > (86400 * 2)) {

                $visitor->fastUpdate('first_visit', \xf::$time);

                $visitor->fastUpdate('latest_visit', \xf::$time);
            }
        }
    }

    public static function awardDailyVisit($visitor) {


        $XpGeneral = \xf::app()->service('XC\RankingSystem:ExperienctPoint');
        $dailyVisit = $XpGeneral->checkXp("visit_site");

        if ($dailyVisit) {
            $serviceGeneral = \xf::app()->service('XC\RankingSystem:General');

            $XpGeneral->AwardXPToUser($dailyVisit, $visitor, $visitor->user_id, true);
        }
    }

    public static function userSearcherOrders(\XF\Searcher\User $userSearcher, array &$sortOrders) {

        $sortOrders['total_points'] = \XF::phrase('xc_experience_points_member_notable');
        $sortOrders['level'] = \XF::phrase('xc_level_member_notable');
    }
}
