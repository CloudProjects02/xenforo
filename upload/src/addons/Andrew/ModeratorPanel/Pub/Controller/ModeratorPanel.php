<?php

namespace Andrew\ModeratorPanel\Pub\Controller;

use XF\Pub\Controller\AbstractController;


class ModeratorPanel extends AbstractController
{

    public function actionIndex()
    {
        return $this->rerouteController('Andrew\ModeratorPanel\Pub\Controller\Dashboard', 'Index');
    }

}