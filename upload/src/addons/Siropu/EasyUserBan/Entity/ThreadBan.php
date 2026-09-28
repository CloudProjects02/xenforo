<?php

namespace Siropu\EasyUserBan\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class ThreadBan extends Entity
{
     public static function getStructure(Structure $structure)
	{
          $structure->table      = 'xf_siropu_easy_user_ban_thread';
          $structure->shortName  = 'EasyUserBan:ThreadBan';
          $structure->primaryKey = ['thread_id', 'user_id'];

          $structure->columns = [
               'thread_id'    => ['type' => self::UINT, 'required' => true],
               'user_id'      => ['type' => self::UINT, 'required' => true],
               'ban_user_id'  => ['type' => self::UINT, 'required' => true],
               'user_reason'  => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
               'ban_date'     => ['type' => self::UINT, 'default' => \XF::$time],
               'end_date'     => ['type' => self::UINT, 'default' => 0]
          ];

          $structure->getters = [
               'ban_reason' => false,
               'ban_length' => false
          ];

		$structure->relations = [
               'Thread' => [
				'entity'     => 'XF:Thread',
				'type'       => self::TO_ONE,
				'conditions' => 'thread_id'
			],
               'User' => [
				'entity'     => 'XF:User',
				'type'       => self::TO_ONE,
				'conditions' => 'user_id'
			],
               'BanUser' => [
				'entity'     => 'XF:User',
				'type'       => self::TO_ONE,
				'conditions' => [['user_id', '=', '$ban_user_id']]
			],
          ];

          $structure->defaultWith = ['Thread', 'User', 'BanUser'];

          return $structure;
     }
     public function getBanType()
     {
          return 'thread';
     }
     public function getLinkParams()
     {
          return ['thread_id' => $this->thread_id, 'ban_type' => 'thread'];
     }
     public function hasExpired()
     {
          $endDate = $this->end_date;

          if ($endDate)
          {
               return $endDate < \XF::$time;
          }
     }
     public function getBanReason()
	{
          return $this->user_reason ?: \XF::phrase('n_a');
     }
     public function getBanLength()
	{
          if ($this->end_date == 0)
          {
               return \XF::phrase('permanent');
          }
          else
          {
               $diff = $this->end_date - $this->ban_date;

               if ($diff >= 3600 && $diff <= 86400)
               {
                    return round($diff / 3600) . ' ' . \XF::phrase('hours');
               }
               else
               {
                    return round($diff / 86400) . ' ' . \XF::phrase('days');
               }
          }
	}
     protected function _postSave()
	{
          if ($this->isInsert())
          {
               $threadBans = $this->User->siropu_easy_user_ban_thread;
               $threadBans[$this->thread_id] = $this->ban_date;

               $this->User->fastUpdate('siropu_easy_user_ban_thread', $threadBans);

               $this->postNotification('ban');
          }
     }
     protected function _postDelete()
	{
          $threadBans = $this->User->siropu_easy_user_ban_thread;
          unset($threadBans[$this->thread_id]);

          $this->User->fastUpdate('siropu_easy_user_ban_thread', $threadBans);

          $this->postNotification('unban');
     }
     public function postNotification($action)
     {
          \XF::service('Siropu\EasyUserBan:ThreadNotifier')->postNotification($this, $action);
     }
}
 		  	   				  		 	 	  	  	  		 	  	   			   	  		 			 		 				  		 				   		 				 		  	 	 	   		  			   	 		 	  		 		  	 	 	  		  	 
