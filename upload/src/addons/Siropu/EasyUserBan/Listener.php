<?php

namespace Siropu\EasyUserBan;

use XF\Mvc\Entity\Entity;

class Listener
{
     public static function criteriaUser($rule, array $data, \XF\Entity\User $user, &$returnValue)
     {
         switch ($rule)
         {
              case 'siropu_easy_user_ban_count':
                   if (isset($user->siropu_easy_user_ban_count) && $user->siropu_easy_user_ban_count >= $data['ban_count'])
                   {
                        $returnValue = true;
                   }
                   break;
         }
     }
     public static function userEntityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure) {}
     public static function userBanEntityPostSave(\XF\Mvc\Entity\Entity $entity)
     {
          self::postNotification($entity, 'ban');

          if ($entity->isInsert())
          {
               $entity->User->fastUpdate('siropu_easy_user_ban_count', $entity->User->siropu_easy_user_ban_count + 1);
          }
     }
     public static function userBanEntityPostDelete(\XF\Mvc\Entity\Entity $entity)
     {
          self::postNotification($entity, 'unban');
     }
     protected static function postNotification($entity, $action)
     {
          \XF::service('Siropu\EasyUserBan:ThreadNotifier')->postNotification($entity, $action);
     }
}
 		  	   				  		 	 	  	  	  		 	  	   			   	  		 			 		 				  		 				   		 				 		  	 	 	   		  			   	 		 	  		 		  	 	 	  		  	 
