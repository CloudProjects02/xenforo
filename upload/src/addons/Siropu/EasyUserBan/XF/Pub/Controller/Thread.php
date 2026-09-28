<?php

namespace Siropu\EasyUserBan\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Thread extends XFCP_Thread
{
     public function actionUserBans(ParameterBag $params)
     {
          $visitor = \XF::visitor();

          if (!$visitor->hasPermission('siropuEasyUserBan', 'viewThreadBans'))
          {
               return $this->noPermission();
          }

          $thread = null;

          $banFinder = $this->finder('Siropu\EasyUserBan:ThreadBan');

          if ($params->thread_id)
          {
               $thread = $this->assertViewableThread($params->thread_id);

               $banFinder->where('thread_id', $thread->thread_id);
          }

          $username = $this->filter('username', 'str');

          if ($username)
          {
               $user = $this->em()->findOne('XF:User', ['username' => $username]);

               if ($user)
               {
                    $banFinder->where('user_id', $user->user_id);
               }
          }

          $page    = $params->page;
          $perPage = 20;

          $banFinder->order('ban_date', 'DESC')->limitByPage($page, $perPage);

          $viewParams = [
               'username' => $username,
               'thread'   => $thread,
               'bans'     => $banFinder->fetch(),
               'total'    => $banFinder->total(),
               'perPage'  => $perPage,
               'page'     => $page
          ];

          return $this->view('XF:Thread\UserBans', 'siropu_easy_user_ban_thread_bans', $viewParams);
     }
     protected function assertViewableThread($threadId, array $extraWith = [])
	{
          $thread = parent::assertViewableThread($threadId, $extraWith);

          if ($this->options()->siropuEasyUserBanThread)
          {
               $visitor = \XF::visitor();

               if ($visitor->isBannedInForum($thread->Forum, $error) || $visitor->isBannedInThread($thread, $error))
               {
                    throw $this->exception($this->message($error));
               }
          }

          return $thread;
     }
}
 		  	   				  		 	 	  	  	  		 	  	   			   	  		 			 		 				  		 				   		 				 		  	 	 	   		  			   	 		 	  		 		  	 	 	  		  	 
