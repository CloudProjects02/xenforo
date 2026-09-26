<?php

namespace XenSoluce\InviteSystem\XF\Pub\Controller;

use XF\ConnectedAccount\ProviderData\AbstractProviderData;
use XF\Mvc\ParameterBag;
use XF\Service\User\RegisterFormService;

class Register extends XFCP_Register
{
    public function actionRegister()
    {
        $code = $this->filter('code','str');
        $error = $this->getErrorInvitation($code);
        if($error)
        {
            return $error;
        }
        return parent::actionRegister();
    }
    public function actionConnectedAccountRegister(ParameterBag $params)
    {
        $code = $this->filter('code','str');
        $error = $this->getErrorInvitation($code);
        if($error)
        {
            return $error;
        }
        return parent::actionConnectedAccountRegister($params);
    }
    protected function getRegistrationInput(RegisterFormService $regForm)
    {
        $input = parent::getRegistrationInput($regForm);
        $input += $this->filter(['code' => 'str']);
        return $input;
    }
    protected function getConnectedRegistrationInput(AbstractProviderData $providerData)
    {
        $input = parent::getConnectedRegistrationInput($providerData);
        $input += $this->filter(['code' => 'str']);
        return $input;
    }
    protected function finalizeRegistration(\XF\Entity\User $user)
    {
        parent::finalizeRegistration($user);
        $alertRepo = $this->repository('XF:UserAlert');
        $code = $this->filter('code','str');
        $codeFinder = $this->finder('XenSoluce\InviteSystem:CodeInvitation')->where('code', $code)->fetchOne();
        $PersonalizedCodeFinder = $this->finder('XenSoluce\InviteSystem:PersonalizedInvitationCode')->where('code', $code)->fetchOne();
        $options = \XF::options();
        $codeTest = false;
        if(!empty($PersonalizedCodeFinder) || !empty($codeFinder) )
        {
            $codeTest = true;
        }
        if($codeTest && $options->xs_is_code_required['mandatory'] == 'yes')
        {
            if(!empty($PersonalizedCodeFinder))
            {
                $registeredUsers = $PersonalizedCodeFinder->registered_user_id;
                $registeredUsers[] = $user->user_id;
                $PersonalizedCodeFinder->registered_user_id = array_values(array_unique($registeredUsers));
                $PersonalizedCodeFinder->save();
            }
            if(!empty($codeFinder))
            {
                $userInvitation = $this->finder('XF:User')->where('user_id', $codeFinder->user_id)->fetchOne();
                $extra = [
                    'link' => \XF::app()->router('public')->buildLink('members', $user),
                    'user' => $user->username
                ];
                $alertRepo->alert($userInvitation, $user->user_id, '', 'user', $user->user_id, 'xs_is_invitations_alert', $extra);
                $userInvitation->xs_is_invite_count += 1;
                $codeFinder->registered_user_id = $user->user_id;
                $codeFinder->save();
                $userInvitation->save();
            }
        }
        elseif ($code && $options->xs_is_code_required['mandatory'] == 'no')
        {
            if(!empty($PersonalizedCodeFinder))
            {
                $registeredUsers = $PersonalizedCodeFinder->registered_user_id;
                $registeredUsers[] = $user->user_id;
                $PersonalizedCodeFinder->registered_user_id = array_values(array_unique($registeredUsers));
                $PersonalizedCodeFinder->save();
            }
            if(!empty($codeFinder))
            {
                $userInvitation = $this->finder('XF:User')->where('user_id', $codeFinder->user_id)->fetchOne();
                $extra = [
                    'link' => \XF::app()->router('public')->buildLink('members', $user),
                    'user' => $user->username
                ];
                $alertRepo->alert($userInvitation, $user->user_id, '', 'user', $user->user_id, 'xs_is_invitations_alert', $extra);
                $userInvitation->xs_is_invite_count += 1;
                $codeFinder->registered_user_id = $user->user_id;
                $codeFinder->save();
                $userInvitation->save();
            }
        }
    }
    protected function getErrorInvitation($code)
    {
        $codeFinder = $this->finder('XenSoluce\InviteSystem:CodeInvitation')
            ->where('code', $code)
            ->fetchOne();
        $PersonalizedCodeFinder = $this->finder('XenSoluce\InviteSystem:PersonalizedInvitationCode')
            ->where([
                'code' => $code,
                'enable' => 1
            ])
            ->fetchOne();
        $options = \XF::options();
        if(!$code && $options->xs_is_code_required['mandatory'] == 'yes')
        {
            return $this->error(\XF::phrase('please_enter_value_for_required_field_x', ['field' => \XF::phrase('xs_is_code')]));
        }
        if($options->xs_is_code_required['mandatory'] == 'yes')
        {
            if(empty($codeFinder) && empty($PersonalizedCodeFinder))
            {
                return $this->error(\XF::phrase('xs_is_invalid_code', ['code' => $code]));
            }
            if(!empty($codeFinder))
            {
                if($codeFinder->registered_user_id)
                {
                    return $this->error(\XF::phrase('xs_is_code_already_used', ['code' => $code]));
                }
            }
            if(!empty($PersonalizedCodeFinder))
            {
                if($PersonalizedCodeFinder->limit_use >= 1)
                {
                    if(count($PersonalizedCodeFinder->registered_user_id) >= $PersonalizedCodeFinder->limit_use)
                    {
                        if($PersonalizedCodeFinder->limit_use == 1)
                        {
                            return $this->error(\XF::phrase('xs_is_code_already_used', ['code' => $code]));
                        }
                        else
                        {
                            return $this->error(\XF::phrase('xs_is_code_reached_maximum_number_registrations', [
                                'code' => $code,
                                'number' => $PersonalizedCodeFinder->limit_use
                                ]));
                        }
                    }
                }
                if($PersonalizedCodeFinder->limit_time >= 1)
                {
                    if($PersonalizedCodeFinder->limit_time <= \XF::$time)
                    {
                        return $this->error(\XF::phrase('xs_is_no_longer_valid', ['code' => $code]));
                    }
                }
            }
        }
        elseif ($code && $options->xs_is_code_required['mandatory'] == 'no')
        {
            if(empty($codeFinder) && empty($PersonalizedCodeFinder))
            {
                return $this->error(\XF::phrase('xs_is_invalid_code', ['code' => $code]));
            }
            if(!empty($codeFinder))
            {
                if($codeFinder->registered_user_id)
                {
                    return $this->error(\XF::phrase('xs_is_code_already_used', ['code' => $code]));
                }
            }
            if(!empty($PersonalizedCodeFinder))
            {
                if($PersonalizedCodeFinder->limit_use >= 1)
                {
                    if(count($PersonalizedCodeFinder->registered_user_id) >= $PersonalizedCodeFinder->limit_use)
                    {
                        if($PersonalizedCodeFinder->limit_use == 1)
                        {
                            return $this->error(\XF::phrase('xs_is_code_already_used', ['code' => $code]));
                        }
                        else
                        {
                            return $this->error(\XF::phrase('xs_is_code_reached_maximum_number_registrations', [
                                'code' => $code,
                                'number' => $PersonalizedCodeFinder->limit_use
                            ]));
                        }
                    }
                }
                if($PersonalizedCodeFinder->limit_time >= 1)
                {
                    if($PersonalizedCodeFinder->limit_time <= \XF::$time)
                    {
                        return $this->error(\XF::phrase('xs_is_no_longer_valid', ['code' => $code]));
                    }
                }
            }
        }
    }
}
