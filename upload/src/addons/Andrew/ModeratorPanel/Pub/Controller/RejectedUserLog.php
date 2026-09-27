<?php

namespace Andrew\ModeratorPanel\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class RejectedUserLog extends AbstractController
{
    public function actionIndex(ParameterBag $params)
    {
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewRejectedUserLog()) {
            return $this->noPermission();
        }

        $this->setSectionContext('rejectedUserLog');

        if ($params->user_id)
        {
            $entry = $this->assertRejectedUserLogExists($params->user_id);

            $viewParams = [
                'entry' => $entry
            ];
            return $this->view('Andrew\ModeratorPanel\RejectedUserLog:View', 'andrew_moderatorpanel_rejected_user_view', $viewParams);
        } else {
            /** @var \XF\Repository\UserReject $rejectRepo */
            $rejectRepo = $this->repository('XF:UserReject');

            $page = $this->filterPage();
            $perPage = 20;

            $finder = $rejectRepo->findUserRejectionsForList()->limitByPage($page, $perPage);

            $viewParams = [
                'rejections' => $finder->fetch(),
                'total' => $finder->total(),
                'page' => $page,
                'perPage' => $perPage
            ];
            return $this->view('Andrew\ModeratorPanel\RejectedUserLog\Listing:View', 'andrew_moderatorpanel_rejected_user_list', $viewParams);
        }
    }

    protected function assertRejectedUserLogExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('XF:UserReject', $id, $with, $phraseKey);
    }
}