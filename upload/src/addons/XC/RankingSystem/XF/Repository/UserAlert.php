<?php

namespace XC\RankingSystem\XF\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class UserAlert extends XFCP_UserAlert {

    public function getAlertHandlers() {

        $parent = parent::getAlertHandlers();

        $routePath = \XF::app()->request()->getRoutePath();

        if (strpos($routePath, "preferences") != false) {

            unset($parent['experience_point']);
            unset($parent['award_badge']);
            unset($parent['claim_usernames_order']);
            unset($parent['claim_usernames_valut']);
        }

        return $parent;
    }
}
