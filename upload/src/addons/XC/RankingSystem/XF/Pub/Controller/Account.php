<?php

namespace XC\RankingSystem\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Account extends XFCP_Account {

    public function actionAccountDetails() {


        $parent = parent::actionAccountDetails();
        if (\XF::options()->xc_show_progress_account) {

            $GLOBALS['allow_to_progressbar'] = true;
         
        }



        return $parent;
    }
}
