<?php

namespace Siropu\EasyUserBan\Service;

class ThreadNotifier extends \XF\Service\AbstractService
{
	public function __construct(\XF\App $app)
	{
		parent::__construct($app);
	}
     public function postNotification($entity, $action)
     {
          $forumId  = $this->app->options()->siropuEasyUserBanForumId;
          $prefixId = $this->app->options()->siropuEasyUserBanThreadPrefix;
		$threadId = $this->app->options()->siropuEasyUserBanThreadId;
          $userId   = $this->app->options()->siropuEasyUserBanUserId;

          $title    = $this->getTitlePhrase($entity, $action);
          $message  = $this->getMessage($entity, $action);

          if ($userId && ($forumId || $threadId))
          {
               $user = \XF::em()->find('XF:User', $userId);

               if ($user)
               {
                    \XF::asVisitor($user, function () use ($forumId, $prefixId, $threadId, $title, $message)
                    {
                         if ($forumId)
               		{
                              $creator = $this->app->service('XF:Thread\Creator', $this->app->em()->find('XF:Forum', $forumId));
                              $creator->setContent($title, $message);
                              $creator->setDiscussionOpen($this->app->options()->siropuEasyUserBanCloseThreads ? 0 : 1);
                              $creator->setIsAutomated();
                              $creator->setPrefix($prefixId);
                              $creator->save();
               		}
               		else if ($threadId)
               		{
               			$replier = $this->app->service('XF:Thread\Replier', $this->app->em()->find('XF:Thread', $threadId));
                              $replier->setMessage($message);
                              $replier->setIsAutomated();
                              $replier->save();
               		}
                    });
               }
          }
     }
     public function getTitlePhrase($entity, $action)
	{
          $visitor = \XF::visitor();

		$phraseParams = [
			'user'      => $entity->User->username,
			'moderator' => $entity->BanUser ? $entity->BanUser->username : $visitor->username
		];

		if ($action == 'ban')
		{
			if ($entity->BanUser)
			{
				$phrase = \XF::phrase('siropu_easy_user_ban_user_x_has_been_banned_by_x', $phraseParams);
			}
			else
			{
				$phrase = \XF::phrase('siropu_easy_user_ban_user_x_has_been_banned_by_warning', $phraseParams);
			}
		}
		else
		{
               if ($entity->end_date && $entity->end_date <= \XF::$time)
               {
                    $phrase = \XF::phrase('siropu_easy_user_ban_user_x_has_been_unbanned_by_the_system', $phraseParams);
               }
               else
               {
                    $phrase = \XF::phrase('siropu_easy_user_ban_user_x_has_been_unbanned_by_x', $phraseParams);
               }
		}

          switch ($entity->getBanType())
          {
               case 'thread':
                    return \XF::phrase('siropu_easy_user_ban_thread_ban') . ' ' . $phrase;
               case 'forum':
                    return \XF::phrase('siropu_easy_user_ban_forum_ban') . ' ' . $phrase;
               default:
                    return $phrase;
          }
	}
     public function getMessage($entity, $action)
	{
          $templater = $this->app->templater();
          $templater->addDefaultParam('xf', $this->app->getGlobalTemplateData());

          return $templater->renderTemplate("public:siropu_easy_user_ban_thread_notification_{$action}", ['ban' => $entity]);
     }
}
 		  	   				  		 	 	  	  	  		 	  	   			   	  		 			 		 				  		 				   		 				 		  	 	 	   		  			   	 		 	  		 		  	 	 	  		  	 
