<?php

namespace SV\SignupAbuseBlocking\XF\Spam\Checker;

use SV\SignupAbuseBlocking\Spam\IScoringChecker;
use SV\StandardLib\Helper;
use XF\Entity\User as UserAlias;
use XF\Spam\ContentChecker;
use function assert;
use function implode;

/**
 * @Extends \XF\Spam\Checker\PreRegAction
 * @property IScoringChecker $checker
 */
class PreRegAction extends XFCP_PreRegAction
{
    public function check(UserAlias $user, array $extraParams = [])
    {
        if (!($this->checker instanceof IScoringChecker))
        {
            parent::check($user, $extraParams);
            return;
        }
        // the parent-function is functionally non-extendable :(

        if (empty($extraParams['preRegActionKey']))
        {
            $this->logDecision('allowed');
            return;
        }

        $action = Helper::repository(\XF\Repository\PreRegAction::class)->getActionByKey($extraParams['preRegActionKey']);
        if (!$action || !$action->Handler)
        {
            $this->logDecision('allowed');
            return;
        }

        $message = $action->Handler->getContentForSpamCheck($action);
        if (!$message)
        {
            $this->logDecision('allowed');
            return;
        }

        $checker = $this->app->spam()->contentChecker();
        $checker->check($user, $message);

        $decision = $checker->getFinalDecision();
        $this->decideAction($checker, $decision);
    }

    protected function decideAction(ContentChecker $checker, $decision)
    {
        switch ($decision)
        {
            case 'moderated':
                $action = \XF::options()->svSignupAbuseBlocking_preregaction_moderate ?? 'moderate';
                break;
            case 'denied':
                $action = \XF::options()->svSignupAbuseBlocking_preregaction_reject ?? 'reject';
                break;
            default:
                $action = null;
                break;
        }

        if ($action === null)
        {
            $this->logDecision('allowed');
            return;
        }

        $details = [];

        foreach ($checker->getDetails() as $detail)
        {
            if (!empty($detail['phrase']))
            {
                $details[] = \XF::phrase($detail['phrase'], $detail['data'] ?? [])->render();
            }
        }

        $this->checker->logScore('sv_reg_log.preregaction_fail', $action, [
            'details' => implode(', ', $details),
        ]);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
