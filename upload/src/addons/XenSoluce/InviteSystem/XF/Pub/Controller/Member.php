<?php


namespace XenSoluce\InviteSystem\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Member extends XFCP_Member
{
    public function actionInvitations(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);
        $page = $this->filterPage();
        $perPage = $this->options()->membersPerPage;
        $Codes = $this->finder('XenSoluce\InviteSystem:CodeInvitation')
            ->where([
                'user_id'=> $user->user_id,
                ['registered_user_id', '!=', '0']
            ])->order('invitation_date', 'desc');
        $Codes->limitByPage($page, $perPage);
        $viewParams = [
            'user'      => $user,
            'Codes'     => $Codes->fetch(),
            'total'     => $Codes->total(),
            'page'      => $page,
            'perPage'   => $perPage
        ];
        return $this->view('XF:Member\Listing', 'xs_is_member_invitation', $viewParams);
    }

}