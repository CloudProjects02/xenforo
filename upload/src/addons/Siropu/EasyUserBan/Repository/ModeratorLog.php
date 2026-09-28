<?php

namespace Siropu\EasyUserBan\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class ModeratorLog extends Repository
{
     public function findModeratorLogs()
     {
          return $this->finder('Siropu\EasyUserBan:ModeratorLog')->order('date', 'DESC');
     }
     public function getModeratorLogUsersForSelect()
     {
          $logs = $this->db()->fetchAll('
               SELECT u.user_id, u.username
               FROM xf_siropu_easy_user_ban_log AS l
               LEFT JOIN xf_user AS u ON u.user_id = l.action_user_id
               GROUP BY l.action_user_id
               ORDER BY u.username ASC
          ');

          $users = [];

          foreach ($logs as $log)
          {
               $users[$log['user_id']] = $log['username'];
          }

          return $users;
     }
     public function prune()
     {
          $this->db()->emptyTable('xf_siropu_easy_user_ban_log');
     }
}
 		  	   				  		 	 	  	  	  		 	  	   			   	  		 			 		 				  		 				   		 				 		  	 	 	   		  			   	 		 	  		 		  	 	 	  		  	 
