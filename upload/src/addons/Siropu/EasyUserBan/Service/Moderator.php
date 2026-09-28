<?php

namespace Siropu\EasyUserBan\Service;

class Moderator extends \XF\Service\AbstractService
{
	protected $user;
	protected $moderator;
	protected $userIps = [];
	protected $input = [];

	public function __construct(\XF\App $app, \XF\Entity\User $user, $moderator = null)
	{
		parent::__construct($app);

          $this->user = $user;
		$this->moderator = $moderator;

		$this->input = $this->app->request->filter([
               'ban_type'     => 'str',
               'node_id'      => 'uint',
               'thread_id'    => 'uint',
			'ban_length'   => 'str',
			'length_value' => 'uint',
			'length_unit'  => 'str',
			'ban_reason'   => 'str',
			'end_date'     => 'datetime',
			'ban_ip'       => 'bool'
		]);

		$this->setUserIps();
	}
	public function setUserIps()
	{
          $visitor = \XF::visitor();

		if ($visitor->hasPermission('siropuEasyUserBanMod', 'banIps'))
		{
			$userIps = $this->getIpRepo()->getIpsByUser($this->user);

			foreach ($userIps as $record)
			{
				$this->userIps[] = \XF\Util\Ip::convertIpBinaryToString($record['ip']);
			}
		}
	}
     public function ban()
     {
          switch ($this->input['ban_type'])
          {
               case 'board':
          		$this->getBanningRepo()->banUser($this->user, $this->getEndDate(), $this->input['ban_reason'], $error);

          		if ($this->input['ban_ip'])
          		{
          			foreach ($this->userIps as $ip)
          			{
          				$this->getBanningRepo()->banIp($ip, $this->input['ban_reason']);
          			}
          		}
                    break;
               case 'forum':
                    $forumBan = \XF::em()->findOne('Siropu\EasyUserBan:ForumBan', [
                         'node_id' => $this->input['node_id'],
                         'user_id' => $this->user->user_id
                    ]);

                    if (!$forumBan)
                    {
                         $forumBan = \XF::em()->create('Siropu\EasyUserBan:ForumBan');
                         $forumBan->set('node_id', $this->input['node_id']);
                    }

                    $forumBan->bulkSet($this->getBanTypeCommonParams());
                    $forumBan->saveIfChanged($saved, false);
                    break;
               case 'thread':
                    $threadBan = \XF::em()->findOne('Siropu\EasyUserBan:ThreadBan', [
                         'thread_id' => $this->input['thread_id'],
                         'user_id'   => $this->user->user_id
                    ]);

                    if (!$threadBan)
                    {
                         $threadBan = \XF::em()->create('Siropu\EasyUserBan:ThreadBan');
                         $threadBan->set('thread_id', $this->input['thread_id']);
                    }

                    $threadBan->bulkSet($this->getBanTypeCommonParams());
                    $threadBan->saveIfChanged($saved, false);
                    break;
          }

		$this->logAction('ban');
     }
     public function unban()
     {
          switch ($this->input['ban_type'])
          {
               case 'board':
                    $ban = \XF::em()->find('XF:UserBan', $this->user->user_id);

                    if ($ban)
                    {
                         $ban->delete();

                         if ($this->userIps)
                         {
                              $ipBans = $this->getBanningRepo()->findIpBans()->where('ip', $this->userIps);

                              foreach ($ipBans->fetch() AS $ipBan)
                              {
                                   $ipBan->delete();
                              }
                         }
                    }
                    break;
               case 'forum':
                    $forumBan = \XF::em()->findOne('Siropu\EasyUserBan:ForumBan', [
                         'node_id' => $this->input['node_id'],
                         'user_id' => $this->user->user_id
                    ]);

                    if ($forumBan)
                    {
                         $forumBan->delete();
                    }
                    break;
               case 'thread':
                    $threadBan = \XF::em()->findOne('Siropu\EasyUserBan:ThreadBan', [
                         'thread_id' => $this->input['thread_id'],
                         'user_id'   => $this->user->user_id
                    ]);

                    if ($threadBan)
                    {
                         $threadBan->delete();
                    }
                    break;
          }

          $this->logAction('unban');
     }
	public function logAction($action = 'ban')
	{
          $banType = $this->input['ban_type'];

          switch ($banType)
          {
               case 'forum':
                    $itemId = $this->input['node_id'];
                    break;
               case 'thread':
                    $itemId = $this->input['thread_id'];
                    break;
               case 'board':
               default:
                    $itemId = 0;
                    break;
          }

		$log = $this->app->em()->create('Siropu\EasyUserBan:ModeratorLog');
		$log->bulkSet([
               'ban_type'       => $banType,
               'item_id'        => intval($itemId),
			'action'         => $action,
			'action_user_id' => $this->moderator ? $this->moderator->user_id : 0,
			'action_reason'  => $this->input['ban_reason'],
			'user_id'        => $this->user->user_id
		]);

		if ($action == 'ban')
		{
			$log->end_date = $this->getEndDate();
		}

		if ($this->input['ban_ip'])
		{
			$log->ips = implode("\n", $this->userIps);
		}

		$log->save();
	}
	public function getEndDate()
	{
		$endDate = $this->input['end_date'];

		if ($this->input['ban_length'] == 'perm')
		{
			$endDate = 0;
		}
		else if (!$endDate)
		{
			$dateTime = new \DateTime('now', new \DateTimeZone(\XF::options()->guestTimeZone));
			$dateTime->modify("+{$this->input['length_value']} {$this->input['length_unit']}");

			$endDate = $dateTime->format('U');
		}

		return $endDate;
	}
     public function getActionUrl($action = 'ban')
     {
          $banType = $this->input['ban_type'];

          $params = [];

          switch ($banType)
          {
               case 'forum':
                    if ($action == 'unban')
                    {
                         $params = ['ban_type' => 'forum', 'node_id' => $this->input['node_id']];
                    }
                    else
                    {
                         $params = ['thread_id' => 0];
                    }
                    break;
               case 'thread':
                    $params = ['thread_id' => $this->input['thread_id']];

                    if ($action == 'unban')
                    {
                         $params['ban_type'] = 'thread';
                    }
                    break;
               case 'board':
                    if ($this->input['thread_id'])
                    {
                         $params = ['thread_id' => $this->input['thread_id']];

                         if ($action == 'unban')
                         {
                              $params['ban_type'] = 'board';
                         }
                    }
                    break;
          }

          return $this->app->router()->buildLink("members/quick-{$action}", $this->user, $params);
     }
     protected function getBanTypeCommonParams()
     {
          return [
               'user_id'     => $this->user->user_id,
               'ban_user_id' => $this->moderator ? $this->moderator->user_id : 0,
               'user_reason' => $this->input['ban_reason'],
               'end_date'    => $this->getEndDate()
          ];
     }
	protected function getBanningRepo()
	{
		return \XF::repository('XF:Banning');
	}
	protected function getIpRepo()
	{
		return \XF::repository('XF:Ip');
	}
}
 		  	   				  		 	 	  	  	  		 	  	   			   	  		 			 		 				  		 				   		 				 		  	 	 	   		  			   	 		 	  		 		  	 	 	  		  	 
