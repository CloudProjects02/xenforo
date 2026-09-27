<?php

namespace Andrew\ModeratorPanel\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class ThreadWarning extends AbstractController
{

    public function actionIndex(ParameterBag $params)
    {

        if (isset($params['thread_id']))
        {
            return $this->rerouteController(__CLASS__, 'view', $params);
        }

    }

    public function actionView(ParameterBag $params)
    {
        $visitor = \XF::visitor();

        if (!$visitor->canViewThreadWarningsMP())
        {
            return $this->noPermission();
        }

        $threadId = $params->thread_id;

        $page = $this->filterPage();
        $perPage = 500;

        $finder = \XF::finder('XF:Thread');
        $thread = $finder->where('thread_id', $threadId)->fetchOne();
        $title = $thread->title;

        $finder = \XF::finder('XF:Warning')
            ->limitByPage($page, $perPage);

        $warnings = $finder
            ->with('User')
            ->with('WarnedBy')
            ->with('Definition')
            ->with('Thread')
            ->where('andrew_mp_thread_id',$threadId)
            ->order('warning_date','DESC')
            ->fetch();


        $viewParams = [
            'title' => $title,
            'warnings' => $warnings,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $finder->total(),
        ];
        return $this->view('Andrew\ModeratorPanel:View', 'andrew_moderatorpanel_warnings_given', $viewParams);
    }
}