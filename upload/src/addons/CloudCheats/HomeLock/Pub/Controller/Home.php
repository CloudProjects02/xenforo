<?php

namespace CloudCheats\HomeLock\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Home extends AbstractController
{
    public function actionIndex(ParameterBag $params)
    {
        $db = \XF::db();

        return $this->view('CloudCheats\HomeLock:Home\Index', 'cloudcheats_home', [
            'memberCount' => (int) $db->fetchOne("SELECT COUNT(*) FROM xf_user WHERE user_state = 'valid'"),
            'threadCount' => (int) $db->fetchOne("SELECT COUNT(*) FROM xf_thread WHERE discussion_state = 'visible'"),
            'postCount'   => (int) $db->fetchOne("SELECT COUNT(*) FROM xf_post WHERE message_state = 'visible'"),
        ]);
    }
}
