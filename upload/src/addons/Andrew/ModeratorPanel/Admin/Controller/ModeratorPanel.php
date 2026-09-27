<?php

namespace Andrew\ModeratorPanel\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\ControllerPlugin\AdminSectionPlugin;

class ModeratorPanel extends AbstractController
{
    public function actionIndex()
    {
        return $this->plugin(AdminSectionPlugin::class)->actionView('moderatorpanel');
    }
}
