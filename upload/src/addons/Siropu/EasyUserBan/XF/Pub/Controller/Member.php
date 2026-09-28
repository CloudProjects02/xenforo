<?php

namespace Siropu\EasyUserBan\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Member extends XFCP_Member
{
     public function actionBanned(ParameterBag $params)
     {
          $visitor = \XF::visitor();

          if (!$visitor->hasPermission('siropuEasyUserBan', 'viewBanned'))
          {
               return $this->noPermission();
          }

          $username = $this->filter('username', 'str');
          $userId   = $this->filter('user_id', 'uint');
          $type     = $this->filter('type', 'str');

          $page     = $this->filterPage($params->page);
          $perPage  = 20;

          $finder = $this->getBanningRepo()
               ->findUserBansForList()
               ->limitByPage($page, $perPage);

          if ($username)
          {
               $user = $this->em()->findOne('XF:User', ['username' => $username]);

               if ($user)
               {
                    $finder->where('user_id', $user->user_id);
               }
               else
               {
                    return $this->message(\XF::phrase('requested_user_not_found'));
               }
          }

          switch ($type)
          {
               case 'perm';
                    $finder->where('end_date', 0);
                    break;
               case 'temp':
                    $finder->where('end_date', '<>', 0);
                    break;
          }

          $linkParams = [];

          $linkParams['type'] = $type;

          $viewParams = [
               'username'   => $username,
               'userBans'   => $finder->fetch(),
               'total'      => $finder->total(),
               'page'       => $page,
               'perPage'    => $perPage,
               'linkParams' => $linkParams,
               'banType'    => $type
          ];

          return $this->view('XF:Member\Banning', 'siropu_easy_user_ban_user_ban_list', $viewParams);
     }
     public function actionModeratorLog(ParameterBag $params)
     {
          $visitor = \XF::visitor();

          if (!$visitor->hasPermission('siropuEasyUserBanMod', 'viewLog'))
          {
               return $this->noPermission();
          }

          $userId     = $this->filter('user_id', 'uint');
          $page       = $this->filterPage($params->page);
          $perPage    = 20;
          $linkParams = [];

          $finder = $this->getModeratorLogRepo()
               ->findModeratorLogs()
               ->limitByPage($page, $perPage);

          if ($userId)
          {
               $finder->where('action_user_id', $userId);

               $linkParams['user_id'] = $userId;
          }

          $viewParams = [
               'entries'    => $finder->fetch(),
               'total'      => $finder->total(),
               'logUsers'   => $this->getModeratorLogRepo()->getModeratorLogUsersForSelect(),
               'page'       => $page,
               'perPage'    => $perPage,
               'linkParams' => $linkParams
          ];

          return $this->view('XF:Member\ModeratorLog', 'siropu_easy_user_ban_moderator_log', $viewParams);
     }
     public function actionModeratorLogDelete(ParameterBag $params)
     {
          $visitor = \XF::visitor();

          if (!$visitor->hasPermission('siropuEasyUserBanMod', 'deleteLogs'))
          {
               return $this->noPermission();
          }

          $log = $this->assertModeratorLogExists($params->log_id);

          if ($this->isPost())
          {
               $log->delete();

               return $this->redirect($this->buildLink('members/moderator-log'));
          }

          $viewParams = [
               'entry' => $log
          ];

          return $this->view('XF:Member\ModeratorLog\Delete', 'siropu_easy_user_ban_moderator_log_delete', $viewParams);
     }
     public function actionManageBans()
     {
          $visitor = \XF::visitor();
          $options = \XF::options();

          if (!($visitor->hasPermission('siropuEasyUserBanMod', 'ban') && $visitor->hasPermission('siropuEasyUserBanMod', 'unban')))
          {
               return $this->noPermission();
          }

          if ($this->isPost())
          {
               $username = array_filter(array_map('trim', explode(',', $this->filter('username', 'str'))));
               $action   = $this->filter('action', 'str');

               if (empty($username))
               {
                    return $this->message('requested_user_not_found');
               }

               if ($action == 'ban' && $options->siropuEasyUserBanRequireReason && empty($this->filter('ban_reason', 'str')))
               {
                    return $this->message(\XF::phrase('siropu_easy_user_ban_please_provide_reason_for_ban'));
               }

               $users = $this->getUserRepo()->getUsersByNames($username, $notFound);

               if ($notFound)
               {
                    return $this->error(\XF::phrase('following_users_not_found_x', ['usernames' => implode(', ', $notFound)]));
               }

               foreach ($users as $user)
               {
                    $moderatorService = $this->service('Siropu\EasyUserBan:Moderator', $user, $visitor);

                    switch ($action)
                    {
                         default:
                         case 'ban':
                              $moderatorService->ban();
                              break;
                         case 'unban':
                              $moderatorService->unBan();
                              break;
                    }
               }

               return $this->redirect($this->buildLink('members/manage-bans'));
          }

          return $this->view('XF:Member\ManageBans', 'siropu_easy_user_ban_manage_bans');
     }
     public function actionQuickBan(ParameterBag $params)
     {
          $visitor = \XF::visitor();
          $options = \XF::options();

          if (!$visitor->hasPermission('siropuEasyUserBanMod', 'ban'))
          {
               return $this->noPermission();
          }

          $user = $this->assertUserExists($params->user_id);

          if ($this->isPost())
          {
               if ($options->siropuEasyUserBanRequireReason && empty($this->filter('ban_reason', 'str')))
               {
                    return $this->message(\XF::phrase('siropu_easy_user_ban_please_provide_reason_for_ban'));
               }

               $moderatorService = $this->service('Siropu\EasyUserBan:Moderator', $user, $visitor);
               $moderatorService->ban();

               $reply = $this->view('XF\Member:QuickBan');
               $reply->setJsonParams([
                    'message'    => \XF::phrase('siropu_easy_user_ban_user_x_has_been_banned', ['user' => $user->username]),
                    'userId'     => $user->user_id,
                    'actionUrl'  => $moderatorService->getActionUrl('unban'),
                    'actionText' => \XF::phrase('lift_ban')
               ]);

               return $reply;
          }

          $viewParams = [
               'user' => $user
          ];

          if ($threadId = $this->filter('thread_id', 'uint'))
          {
               $viewParams['thread'] = $this->em()->find('XF:Thread', $threadId);
          }

          return $this->view('XF:Member\QuickBan', 'siropu_easy_user_ban_quick_ban', $viewParams);
     }
     public function actionQuickUnban(ParameterBag $params)
     {
          $visitor = \XF::visitor();

          if (!$visitor->hasPermission('siropuEasyUserBanMod', 'unban'))
          {
               return $this->noPermission();
          }

          $input = $this->filter([
               'ban_type'  => 'str',
               'node_id'   => 'uint',
               'thread_id' => 'uint'
          ]);

          $banType = $input['ban_type'] ?: 'board';

          switch ($banType)
          {
               case 'forum':
                    $userBan = $this->assertForumBanExists(['node_id' => $input['node_id'], 'user_id' => $params->user_id]);
                    break;
               case 'thread':
                    $userBan = $this->assertThreadBanExists(['thread_id' => $input['thread_id'], 'user_id' => $params->user_id]);
                    break;
               case 'board':
               default:
                    $userBan = $this->assertUserBanExists($params->user_id);
                    break;
          }

          if ($this->isPost())
          {
               $moderatorService = $this->service('Siropu\EasyUserBan:Moderator', $userBan->User, $visitor);
               $moderatorService->unBan();

               $reply = $this->view('XF\Member:QuickBan');
               $reply->setJsonParams([
                    'message'    => \XF::phrase('siropu_easy_user_ban_user_x_has_been_unbanned', ['user' => $userBan->User->username]),
                    'userId'     => $userBan->user_id,
                    'actionUrl'  => $moderatorService->getActionUrl('ban'),
                    'actionText' => \XF::phrase('ban')
               ]);

               return $reply;
          }

          $viewParams = [
               'userBan' => $userBan,
               'banType' => $banType
          ];

          if ($threadId = $this->filter('thread_id', 'uint'))
          {
               $viewParams['thread'] = $this->em()->find('XF:Thread', $threadId);
          }
          else if ($nodeId = $this->filter('node_id', 'uint'))
          {
               $viewParams['forum'] = $this->em()->find('XF:Forum', $nodeId);
          }

          return $this->view('XF:Member\QuickUnban', 'siropu_easy_user_ban_quick_unban', $viewParams);
     }
     public function actionBanIp()
     {
          $visitor = \XF::visitor();

		if (!$visitor->hasPermission('siropuEasyUserBanMod', 'banIps'))
		{
               return $this->noPermission();
          }

          if ($this->isPost())
          {
               $this->getBanningRepo()->banIp($this->filter('ip', 'str'), $this->filter('reason', 'str'));

               return $this->message(\XF::phrase('siropu_easy_user_ban_ip_has_been_banned'));
          }

          $ipEntity = $this->em()->find('XF:Ip', $this->filter('ip_id', 'uint'));

          $viewParams = [
               'ip' => $ipEntity
          ];

          return $this->view('XF:Member:BanIp', 'siropu_easy_user_ban_ip_ban', $viewParams);
     }
     public function getModeratorLogRepo()
     {
          return $this->repository('Siropu\EasyUserBan:ModeratorLog');
     }
     public function getUserRepo()
     {
          return $this->repository('XF:User');
     }
     public function getBanningRepo()
     {
          return $this->repository('XF:Banning');
     }
     protected function assertModeratorLogExists($id, $with = null)
	{
		return $this->assertRecordExists('Siropu\EasyUserBan:ModeratorLog', $id, $with, 'siropu_easy_user_ban_requested_log_not_found');
	}
     protected function assertUserExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists('XF:User', $id, $with, 'requested_user_not_found');
	}
     protected function assertUserBanExists($id, $with = null)
	{
		return $this->assertRecordExists('XF:UserBan', $id, $with, 'siropu_easy_user_ban_requested_ban_not_found');
	}
     protected function assertForumBanExists(array $key)
	{
		$forumBan = $this->em()->findOne('Siropu\EasyUserBan:ForumBan', $key);

          if (!$forumBan)
          {
               throw $this->exception($this->notFound(\XF::phrase('siropu_easy_user_ban_requested_forum_ban_not_found')));
          }

          return $forumBan;
	}
     protected function assertThreadBanExists(array $key)
	{
		$threadBan = $this->em()->findOne('Siropu\EasyUserBan:ThreadBan', $key);

          if (!$threadBan)
          {
               throw $this->exception($this->notFound(\XF::phrase('siropu_easy_user_ban_requested_thread_ban_not_found')));
          }

          return $threadBan;
	}
}
 		  	   				  		 	 	  	  	  		 	  	   			   	  		 			 		 				  		 				   		 				 		  	 	 	   		  			   	 		 	  		 		  	 	 	  		  	 
