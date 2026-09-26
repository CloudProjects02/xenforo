<?php

namespace XenSoluce\InviteSystem\XF\Pub\Controller;

class Account extends XFCP_Account
{
    public function actionInvitation()
    {
        $visitor = \XF::visitor();
        if(!$visitor->hasPermission('xs_is', 'xs_is_can_invite_someone'))
        {
            return $this->noPermission();
        }
        $Ban = $this->finder('XenSoluce\InviteSystem:Banning')
            ->where('user_id', $visitor->user_id)
            ->fetchOne();
        if (!empty($Ban))
        {
            $viewParams = ['Ban' => $Ban];
            return $this->view('XenSoluce\InviteSystem:Invitation', 'xs_is_account_Invitation_ban', $viewParams);
        }

        if ($this->isPost())
        {
            $InvitationPerMonth = $this->InvitationPerMonth($visitor);
            if(!$InvitationPerMonth['Between'])
            {
                return $this->message(\XF::phrase('xs_is_general_code_limit_reached', ['PerMonth' => $InvitationPerMonth['PerMonth']]));
            }
            $token = $this->filter([
                'token'=> 'str',
                'tokenID' => 'int'
            ]);

            $Code = $this->em()->create('XenSoluce\InviteSystem:CodeInvitation');
            $Code->user_id = $visitor->user_id;
            $Code->token_id = $token['tokenID'];
            $Code->token = $token['token'];
            $Code->type_code = 1;
            $Code->save();
            return $this->redirect($this->buildLink('account/invitation') . '?&code=' . $Code->code);

        }
        else
        {
            $Invitations = $this->Invitation($visitor);
            $options = \XF::options();
            $page = $this->filterPage();
            $perPage = $options->xs_is_perPage_account;
            $Codes = $this->finder('XenSoluce\InviteSystem:CodeInvitation')
                ->order('code_id', 'DESC')
                ->where([
                    'user_id'=> $visitor->user_id,
                    ['type_code', '!=', '2']
                ]);
            $Codes->limitByPage($page, $perPage);
            $viewParams = [
                'Invitations' => $Invitations,
                'code' => $this->filter('code', 'str'),
                'Codes' => $Codes->fetch(),
                'total' => $Codes->total(),
                'page' => $page,
                'perPage' => $perPage
            ];

            $view = $this->view('XenSoluce\InviteSystem:Invitation', 'xs_is_account_Invitation', $viewParams);
            return $this->addAccountWrapperParams($view, 'xs_Invitation');
        }
    }
    protected function Invitation(\XF\Entity\User $visitor)
    {

        $Tokens = $this->finder('XenSoluce\InviteSystem:Token')
            ->order('token_id', 'DESC');
        $Invitations['tokenGenerate'] = null;
        $Invitations['total'] = 0;
        $Invitations += $this->InvitationPerMonth($visitor);
        foreach ($Tokens->fetch() as $Token)
        {
            $isCount = 0;
            $isToken = false;
            $isNumber = false;
            $Codes = $this->finder('XenSoluce\InviteSystem:CodeInvitation')
                ->where([
                    'user_id'=> $visitor->user_id,
                    'token_id' => $Token->token_id
                ]);
            if($Token->number_use == '1')
            {
                $isToken = empty($Codes->fetchOne());
            }
            elseif($Token->number_use > '1')
            {
                if(count($Codes->fetch()) < $Token->number_use)
                {
                    $isToken = true;
                }
                $isNumber = true;
            }
            if(in_array($visitor->user_id, $Token->user) && $isToken &&  $Token->type_token == '2')
            {
                $Invitations['tokenGenerate'] = [
                    'token' => $Token->token,
                    'tokenID' => $Token->token_id,
                ];
                if(!$isNumber)
                {
                    $isCount = 1;
                }
                else
                {
                    $isCount = 2;
                }
            }
            if(in_array($visitor->user_group_id, $Token->user) && $isToken &&  $Token->type_token == '1')
            {
                $Invitations['tokenGenerate'] = [
                    'token' => $Token->token,
                    'tokenID' => $Token->token_id,
                ];
                if(!$isNumber)
                {
                    $isCount = 1;
                }
                else
                {
                    $isCount = 2;
                }
            }
            foreach ($visitor->secondary_group_ids as $group)
            {
                if(in_array($group, $Token->user) && $isToken &&  $Token->type_token == '1')
                {
                    $Invitations['tokenGenerate'] = [
                        'token' => $Token->token,
                        'tokenID' => $Token->token_id,
                    ];
                    if(!$isNumber)
                    {
                        $isCount = 1;
                    }
                    else
                    {
                        $isCount = 2;
                    }
                }
            }
            if($isCount === 1)
            {
                $Invitations['total']++;
            }
            elseif($isCount === 2)
            {
                $Invitations['total'] += $Token->number_use-count($Codes->fetch());
            }
        };
        return $Invitations;
    }
    protected function InvitationPerMonth(\XF\Entity\User $visitor)
    {
        $options = \XF::options();
        $invitationsPerMonth = $visitor->hasPermission('xs_is', 'xs_is_can_invite_per_m');
        $timeZone = new \DateTimeZone($options->guestTimeZone);
        $Invitations['Between'] = true;
        if($invitationsPerMonth > 0)
        {
            $dateTime = new \DateTime('first day of this month 00:00', $timeZone);
            $CodeInvitationCount = $this->finder('XenSoluce\InviteSystem:CodeInvitation')
                ->where([
                    'user_id'=> $visitor->user_id,
                    ['type_code', '!=', '2']
                ])
                ->dateBetweenInvitation($dateTime->format('U'), \XF::$time)
                ->total();
            if($invitationsPerMonth <= $CodeInvitationCount)
            {
                $Invitations = [
                    'Between' => false,
                    'PerMonth' => $invitationsPerMonth
                ];
            }
        }
        return $Invitations;
    }
}
