<?php

namespace Siropu\EasyUserBan\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class ForumBan extends Entity
{
     public static function getStructure(Structure $structure)
	{
          $structure->table      = 'xf_siropu_easy_user_ban_forum';
          $structure->shortName  = 'EasyUserBan:NodeBan';
          $structure->primaryKey = ['node_id', 'user_id'];

          $structure->columns = [
               'node_id'      => ['type' => self::UINT, 'required' => true],
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
               'Forum' => [
				'entity'     => 'XF:Forum',
				'type'       => self::TO_ONE,
				'conditions' => 'node_id'
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

          $structure->defaultWith = ['Forum', 'User', 'BanUser'];

          return $structure;
     }
     public function getBanType()
     {
          return 'forum';
     }
     public function getLinkParams()
     {
          return ['node_id' => $this->node_id, 'ban_type' => 'forum'];
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
               $forumBans = $this->User->siropu_easy_user_ban_forum;
               $forumBans[$this->node_id] = $this->ban_date;

               $this->User->fastUpdate('siropu_easy_user_ban_forum', $forumBans);

               $this->postNotification('ban');
          }
     }
     protected function _postDelete()
	{
          $forumBans = $this->User->siropu_easy_user_ban_forum;
          unset($forumBans[$this->node_id]);

          $this->User->fastUpdate('siropu_easy_user_ban_forum', $forumBans);

          $this->postNotification('unban');
     }
     public function postNotification($action)
     {
          \XF::service('Siropu\EasyUserBan:ThreadNotifier')->postNotification($this, $action);
     }
}
 		  	   				  		 	 	  	  	  		 	  	   			   	  		 			 		 				  		 				   		 				 		  	 	 	   		  			   	 		 	  		 		  	 	 	  		  	 
