<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\User;

use SV\SignupAbuseBlocking\Repository\AntiSpam as AntiSpamRepo;
use SV\StandardLib\BypassAccessStatus;
use XF\Entity\User as UserEntity;
use XF\Spam\Checker\AbstractProvider;
use XF\Spam\Checker\UserCheckerInterface;
use XF\Spam\ContentChecker as ContentSpamChecker;
use function array_key_exists;
use function implode;
use function strlen;
use function trim;

/**
 * Class UserField
 *
 * @package SV\SignupAbuseBlocking\Spam\Checker
 */
class UserField extends AbstractProvider implements UserCheckerInterface
{
    protected function getType(): string
    {
        return 'SignupAbuseUserField';
    }

    public function check(UserEntity $user, array $extraParams = [])
    {
        $profile = $user->Profile;
        if (!$profile)
        {
            $this->logDecision('allowed');
            return;
        }

        $antiSpamRepo = AntiSpamRepo::get();
        $customFields = $antiSpamRepo->getSpammableCustomFields($user, []);
        $profileFields = $antiSpamRepo->getSpammableProfileFields($user, false);
        $fields = array_merge($customFields, $profileFields);

        $message = trim(implode(' ', $fields));
        if (strlen($message) === 0)
        {
            $this->logDecision('allowed');
            return;
        }

        $contentSpamChecker = $this->contentSpamChecker();
        $contentSpamChecker->check($user, $message, [
            'content_type' => 'user' // see \XF\Spam\Checker\Akismet for content types
        ]);

        $accesser = new BypassAccessStatus();
        $decisions = $accesser->getPrivate($contentSpamChecker, 'decisions')();
        $details = $accesser->getPrivate($contentSpamChecker, 'details')();
        $params = $accesser->getPrivate($contentSpamChecker, 'params')();

        foreach ($decisions AS $type => $decision)
        {
            $this->checker->logDecision($type, $decision);
        }
        foreach ($details AS $type => $detail)
        {
            $phrase = $detail['phrase'];
            $data = [];
            if (array_key_exists('data', $detail))
            {
                $data = $detail['data'];
            }

            $this->checker->logDetail($type, $phrase, $data);
        }
        foreach ($params AS $key => $param)
        {
            $this->checker->logParam($key, $param);
        }
    }

    /**
     * @param UserEntity $user
     * @param array      $extraParams
     */
    public function submit(UserEntity $user, array $extraParams = [])
    {
    }

    protected function contentSpamChecker(): ContentSpamChecker
    {
        return $this->app()->spam()->contentChecker();
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
