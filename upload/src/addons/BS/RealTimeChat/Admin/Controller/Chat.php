<?php

namespace BS\RealTimeChat\Admin\Controller;

use XF\Admin\Controller\AbstractController;

class Chat extends AbstractController
{
    public function actionIndex()
    {
        return $this->view('BS\RealTimeChat:Index', 'real_time_chat');
    }
}