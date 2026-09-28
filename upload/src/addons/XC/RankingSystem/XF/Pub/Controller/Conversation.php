<?php

namespace XC\RankingSystem\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Conversation extends XFCP_Conversation {

    public function actionView(ParameterBag $params) {
        $parent = parent::actionView($params);

        if (\XF::options()->xc_show_progress_conversation) {
            $GLOBALS['allow_to_progressbar'] =true;
        }
        
        if(\xf::options()->xc_show_unlock_badges){
            
            
        }
        return $parent;
    }

}
