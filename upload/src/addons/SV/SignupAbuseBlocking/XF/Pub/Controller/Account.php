<?php

namespace SV\SignupAbuseBlocking\XF\Pub\Controller;

use SV\SignupAbuseBlocking\Repository\AntiSpam as AntiSpamRepo;
use XF\Entity\User as UserEntity;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use function array_merge;
use function implode;
use function strlen;
use function trim;

/**
 * @Extends \XF\Pub\Controller\Account
 */
class Account extends XFCP_Account
{
    protected $actionL = null;

    public function preDispatch($action, ParameterBag $params)
    {
        $this->actionL = \strtolower($action);
        parent::preDispatch($action, $params);
    }

    public function assertNotBanned()
    {
        if ((\XF::options()->svAllowBannedLogout ?? false) && $this->actionL === 'visitormenu')
        {
            return;
        }
        parent::assertNotBanned();
    }

    public function assertNotRejected($action)
    {
        if ((\XF::options()->svAllowRejectedLogout ?? false) && $this->actionL === 'visitormenu')
        {
            return;
        }

        parent::assertNotRejected($action);
    }


    /** @noinspection PhpMissingReturnTypeInspection */
    protected function accountDetailsSaveProcess(UserEntity $visitor)
    {
        $form = parent::accountDetailsSaveProcess($visitor);

        $form->validate(function(FormAction $form) use($visitor)
        {
            if ($visitor->isSpamCheckRequired())
            {
                $antiSpamRepo = AntiSpamRepo::get();
                $customFields = $antiSpamRepo->getSpammableCustomFields($visitor, ['personal', 'contact']);
                $profileFields = $antiSpamRepo->getSpammableProfileFields($visitor, true);
                $fields = array_merge($customFields, $profileFields);

                if (count($fields) === 0)
                {
                    return;
                }
                $message = trim(implode(' ', $fields));
                if (strlen($message) === 0)
                {
                    return;
                }

                $checker = $this->app()->spam()->contentChecker();
                $checker->check($visitor, $message, [
                    'content_type' => 'user',
                    'content_id' => $visitor->user_id
                ]);

                $decision = $checker->getFinalDecision();
                switch ($decision)
                {
                    case 'moderated':
                    case 'denied':
                        $checker->logSpamTrigger('user_about', $visitor->user_id);
                        $form->logError(\XF::phrase('your_content_cannot_be_submitted_try_later'));
                        break;
                }
            }
        });

        return $form;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
