<?php

namespace Siropu\EasyUserBan\XF\Entity;

use XF\Mvc\Entity\Structure;

class User extends XFCP_User
{
     public function isBannedInForum(\XF\Entity\Forum $forum, &$error = null)
     {
          $forumBan = $this->UserForumBans[$forum->node_id] ?? null;

          if ($forumBan)
          {
               if ($forumBan->hasExpired())
               {
                    $forumBan->delete();
               }
               else
               {
                    $endDate = $forumBan->end_date;

                    if ($endDate)
                    {
                         if ($forumBan->user_reason)
                         {
                              $phrase = 'siropu_easy_user_ban_you_are_banned_from_forum_x_until_y_for_reason_z';
                         }
                         else
                         {
                              $phrase = 'siropu_easy_user_ban_you_are_banned_from_forum_x_until_y';
                         }

                         $error = \XF::phrase($phrase, [
                              'title'  => $forum->title,
                              'date'   => \XF::language()->dateTime($endDate),
                              'reason' => $forumBan->user_reason
                         ]);

                         return true;
                    }
                    else
                    {
                         if ($forumBan->user_reason)
                         {
                              $error = \XF::phrase('siropu_easy_user_ban_you_are_banned_from_this_forum_permanently_for_reason_x', [
                                   'reason' => $forumBan->user_reason
                              ]);
                         }
                         else
                         {
                              $error = \XF::phrase('siropu_easy_user_ban_you_are_banned_from_this_forum_permanently');
                         }

                         return true;
                    }
               }
          }
     }
     public function isBannedInThread(\XF\Entity\Thread $thread, &$error = null)
     {
          $threadBan = $this->UserThreadBans[$thread->thread_id] ?? null;

          if ($threadBan)
          {
               if ($threadBan->hasExpired())
               {
                    $threadBan->delete();
               }
               else
               {
                    $endDate = $threadBan->end_date;

                    if ($endDate)
                    {
                         if ($threadBan->user_reason)
                         {
                              $phrase = 'siropu_easy_user_ban_you_are_banned_from_this_thread_until_x_for_reason_y';
                         }
                         else
                         {
                              $phrase = 'siropu_easy_user_ban_you_are_banned_from_this_thread_until_x';
                         }

                         $error = \XF::phrase($phrase, [
                              'date'   => \XF::language()->dateTime($endDate),
                              'reason' => $threadBan->user_reason
                         ]);

                         return true;
                    }
                    else
                    {
                         if ($threadBan->user_reason)
                         {
                              $error = \XF::phrase('siropu_easy_user_ban_you_are_banned_from_this_thread_permanently_for_reason_x', [
                                   'reason' => $threadBan->user_reason
                              ]);
                         }
                         else
                         {
                              $error = \XF::phrase('siropu_easy_user_ban_you_are_banned_from_this_thread_permanently');
                         }

                         return true;
                    }
               }
          }
     }
     public function isForumIdBanned($forumId)
     {
          return $this->siropu_easy_user_ban_forum[$forumId] ?? false;
     }
     public function isThreadIdBanned($threadId)
     {
          return $this->siropu_easy_user_ban_thread[$threadId] ?? false;
     }
     public function getAvatarUrl($sizeCode, $forceType = null, $canonical = false)
     {
          if ($this->is_banned && $this->app()->options()->siropuEasyUserBanBannedAvatar)
          {
               switch ($sizeCode)
               {
                    default:
                    case 's':
                         $avatar = $this->app()->options()->siropuEasyUserBanSmallAvatar;
                         break;
                    case 'm':
                         $avatar = $this->app()->options()->siropuEasyUserBanMediumAvatar;
                         break;
                    case 'l':
                         $avatar = $this->app()->options()->siropuEasyUserBanLargeAvatar;
                         break;
               }

               if (stripos($avatar, 'http') === false)
               {
                    return $this->app()->applyExternalDataUrl($avatar);
               }
               else
               {
                    return $avatar;
               }
          }

          return parent::getAvatarUrl($sizeCode, $forceType, $canonical);
     }
     public function getAvatarType()
     {
          if ($this->is_banned && $this->app()->options()->siropuEasyUserBanBannedAvatar)
          {
               return 'custom';
          }

          return parent::getAvatarType();
     }
     public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

          $structure->columns['siropu_easy_user_ban_count']  = ['type' => self::UINT, 'default' => 0, 'changeLog' => false];
          $structure->columns['siropu_easy_user_ban_forum']  = ['type' => self::JSON_ARRAY, 'default' => [], 'changeLog' => false];
          $structure->columns['siropu_easy_user_ban_thread'] = ['type' => self::JSON_ARRAY, 'default' => [], 'changeLog' => false];

          $structure->relations['UserForumBans'] = [
			'entity'     => 'Siropu\EasyUserBan:ForumBan',
			'type'       => self::TO_MANY,
			'conditions' => 'user_id',
               'key'        => 'node_id'
		];

          $structure->relations['UserThreadBans'] = [
			'entity'     => 'Siropu\EasyUserBan:ThreadBan',
			'type'       => self::TO_MANY,
			'conditions' => 'user_id',
               'key'        => 'thread_id'
		];

		return $structure;
	}
}
 		  	   				  		 	 	  	  	  		 	  	   			   	  		 			 		 				  		 				   		 				 		  	 	 	   		  			   	 		 	  		 		  	 	 	  		  	 
