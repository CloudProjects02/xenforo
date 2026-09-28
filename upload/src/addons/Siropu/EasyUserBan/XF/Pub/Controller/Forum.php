<?php

namespace Siropu\EasyUserBan\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Forum extends XFCP_Forum
{
     public function actionUserBans(ParameterBag $params)
     {
          $visitor = \XF::visitor();

          if (!$visitor->hasPermission('siropuEasyUserBan', 'viewForumBans'))
          {
               return $this->noPermission();
          }

          $forum = null;

          $banFinder = $this->finder('Siropu\EasyUserBan:ForumBan');

          if ($params->node_id)
          {
               $forum = $this->assertViewableForum($params->node_id);

               $banFinder->where('node_id', $params->node_id);
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
               'forum'    => $forum,
               'bans'     => $banFinder->fetch(),
               'total'    => $banFinder->total(),
               'perPage'  => $perPage,
               'page'     => $page
          ];

          return $this->view('XF:Forum\UserBans', 'siropu_easy_user_ban_forum_bans', $viewParams);
     }
     protected function assertViewableForum($nodeIdOrName, array $extraWith = [])
	{
          $forum = parent::assertViewableForum($nodeIdOrName, $extraWith);

          if ($this->options()->siropuEasyUserBanForum)
          {
               $visitor = \XF::visitor();

               if ($visitor->isBannedInForum($forum, $error))
               {
                    throw $this->exception($this->message($error));
               }
          }

          return $forum;
     }
}
 		  	   				  		 	 	  	  	  		 	  	   			   	  		 			 		 				  		 				   		 				 		  	 	 	   		  			   	 		 	  		 		  	 	 	  		  	 
