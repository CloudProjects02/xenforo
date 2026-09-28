<?php

namespace Siropu\EasyUserBan\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class ModeratorLog extends Entity
{
     public static function getStructure(Structure $structure)
	{
          $structure->table      = 'xf_siropu_easy_user_ban_log';
          $structure->shortName  = 'EasyUserBan:ModeratorLog';
          $structure->primaryKey = 'log_id';

          $structure->columns = [
               'log_id'         => ['type' => self::UINT, 'autoIncrement' => true],
               'ban_type'       => ['type' => self::STR, 'allowedValues' => ['board', 'forum', 'thread']],
               'item_id'        => ['type' => self::UINT, 'default' => 0],
               'action'         => ['type' => self::STR, 'allowedValues' => ['ban', 'unban'], 'default' => 'ban'],
               'action_user_id' => ['type' => self::UINT, 'required' => true],
               'action_reason'  => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
               'user_id'        => ['type' => self::UINT, 'required' => true],
               'ips'            => ['type' => self::STR, 'default' => ''],
               'date'           => ['type' => self::UINT, 'default' => \XF::$time],
               'end_date'       => ['type' => self::UINT, 'default' => 0],
          ];

          $structure->getters   = [
               'ban_type_phrase' => true,
               'ban_reason'      => false,
               'ban_length'      => false,
               'ban_ips'         => false
          ];

		$structure->relations = [
               'User' => [
				'entity'     => 'XF:User',
				'type'       => self::TO_ONE,
				'conditions' => 'user_id'
			],
               'Moderator' => [
				'entity'     => 'XF:User',
				'type'       => self::TO_ONE,
				'conditions' => [['user_id', '=', '$action_user_id']]
			],
               'Forum' => [
                    'entity'     => 'XF:Forum',
                    'type'       => self::TO_ONE,
                    'conditions' => [
                         ['$ban_type', '=', 'forum'],
                         ['node_id', '=', '$item_id']
                    ]
               ],
               'Thread' => [
                    'entity'     => 'XF:Thread',
                    'type'       => self::TO_ONE,
                    'conditions' => [
                         ['$ban_type', '=', 'thread'],
                         ['thread_id', '=', '$item_id']
                    ]
               ]
          ];

          $structure->defaultWith = ['User', 'Moderator', 'Forum', 'Thread'];

          return $structure;
     }
     public function isBoardBan()
     {
          return $this->ban_type == 'board';
     }
     public function isForumBan()
     {
          return $this->ban_type == 'forum';
     }
     public function isThreadBan()
     {
          return $this->ban_type == 'thread';
     }
     public function isBan()
     {
          return $this->action == 'ban';
     }
     public function isUnban()
     {
          return $this->action == 'unban';
     }
     public function getBanTypePhrase()
     {
          $type = $this->ban_type ?: 'board';

          return \XF::phrase("siropu_easy_user_ban_type.{$type}");
     }
     public function getBanReason()
	{
          return $this->action_reason ?: \XF::phrase('n_a');
     }
     public function getBanLength()
	{
          if ($this->isUnban())
          {
               return '--';
          }

          if ($this->end_date == 0)
          {
               return \XF::phrase('permanent');
          }
          else
          {
               $diff = $this->end_date - $this->date;

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
     public function getBanIps()
	{
          return $this->ips ?: '--';
     }
}
 		  	   				  		 	 	  	  	  		 	  	   			   	  		 			 		 				  		 				   		 				 		  	 	 	   		  			   	 		 	  		 		  	 	 	  		  	 
