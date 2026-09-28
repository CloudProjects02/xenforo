<?php

namespace SV\SignupAbuseBlocking\XF\Pub\Controller;

use SV\SignupAbuseBlocking\ControllerPlugin\MultipleAccount as MultipleAccountPlugin;
use SV\SignupAbuseBlocking\Entity\Log as LogEntity;
use SV\SignupAbuseBlocking\XF\Entity\User as ExtendedUserEntity;
use SV\StandardLib\Helper;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/**
 * Class Member
 *
 * @package SV\SignupAbuseBlocking\XF\Pub\Controller
 */
class Member extends XFCP_Member
{
    public function actionMultipleAccountList(ParameterBag $parameterBag): AbstractReply
    {
        /** @var ExtendedUserEntity $visitor */
        $visitor = \XF::visitor();
        if (!$visitor->canViewMultiAccountReport())
        {
            throw $this->exception($this->notFound());
        }
        $user = $this->assertViewableUser($parameterBag->user_id ?? 0);

        $additionalLinkParams = [];
        $reportDataId = (int)$this->filter('report_data_id', 'uint');
        if ($reportDataId !== 0)
        {
            $additionalLinkParams['report_data_id'] = $reportDataId;
        }
        $additionalLinkParams['page'] = $this->filterPage($parameterBag->page ?? 0);

        $multipleAccountPlugin = Helper::plugin($this, MultipleAccountPlugin::class);

        return $multipleAccountPlugin->actionMultipleAccountEventList(
            $user,
            'members/multiple-account-list',
            'SV\SignupAbuseBlocking\XF:Member\MultipleAccountList',
            $additionalLinkParams
        );
    }

    public function actionMultiAccountToggleUserAlerting(ParameterBag $params): AbstractReply
    {
        /** @var ExtendedUserEntity $user */
        $user = $this->assertViewableUser($params->user_id ?? 0);

        // return not found just in case someone tries being funny
        if (!$user->canChangeUserMultiAccountAlerting())
        {
            return $this->notFound();
        }

        $isAlertable = !$user->Profile->multiple_account_detection_alertable;

        if ($this->isPost())
        {
            $user->Profile->multiple_account_detection_alertable = $isAlertable;
            if ($isAlertable)
            {
                $text = \XF::phrase('sv_multiple_account_alerting.user_enable');
            }
            else
            {
                $text = \XF::phrase('sv_multiple_account_alerting.user_disable');
            }

            $user->Profile->saveIfChanged();

            $reply = $this->redirect($this->getDynamicRedirect());
            $reply->setJsonParam('switchKey', $isAlertable ? 'enable' : 'disable');
            $reply->setJsonParams([
                'text'        => $text,
                'isAlertable' => $isAlertable,
            ]);

            return $reply;
        }

        $viewParams = [
            'user' => $user,

            'is_alertable' => $isAlertable
        ];

        return $this->view('SV\SignupAbuseBlocking\XF:User\SuppressToggle', 'sv_multiple_account_user_confirm', $viewParams);
    }

    public function actionMultiAccountToggleLogAlerting(ParameterBag $params): AbstractReply
    {
        $user = $this->assertViewableUser($params->get('user_id'));
        $log = $this->assertViewableUserWithMultiAccountLog($this->filter('log_id', 'uint'));

        if (!$log->canChangeAlerting())
        {
            return $this->notFound();
        }

        if ($this->isPost())
        {
            $log->is_alertable = !$log->is_alertable;
            if ($log->is_alertable)
            {
                $text = \XF::phrase('sv_multiple_account_alerting.log_disable');
            }
            else
            {
                $text = \XF::phrase('sv_multiple_account_alerting.log_enable');
            }
            $log->save();

            $reply = $this->redirect($this->getDynamicRedirect());
            $reply->setJsonParam('switchKey', $log->is_alertable ? 'enable' : 'disable');
            $reply->setJsonParams([
                'text'        => $text,
                'isAlertable' => $log->is_alertable,
            ]);

            return $reply;
        }

        $skipAlert = $log->is_alertable;

        $viewParams = [
            'user' => $user,
            'log'  => $log,
            'is_alertable' => $skipAlert
        ];

        return $this->view('SV\SignupAbuseBlocking\XF:User\SuppressToggle', 'sv_multiple_account_log_confirm', $viewParams);
    }

    protected function assertViewableUserWithMultiAccountLog(int $logId, array $extraWith = []): LogEntity
    {
        $log = Helper::find(LogEntity::class, $logId, $extraWith);
        if ($log === null)
        {
            throw $this->exception($this->notFound());
        }

        return $log;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
