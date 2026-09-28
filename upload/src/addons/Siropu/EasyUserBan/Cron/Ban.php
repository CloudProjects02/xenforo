<?php

namespace Siropu\EasyUserBan\Cron;

class Ban
{
	public static function deleteExpiredBans()
	{
          $options = \XF::options();

          if ($options->siropuEasyUserBanForum)
          {
               $forumBans = \XF::finder('Siropu\EasyUserBan:ForumBan')
                    ->where('end_date', '>', 0)
                    ->where('end_date', '<', \XF::$time)
                    ->fetch();

               foreach ($forumBans as $forumBan)
               {
                    $forumBan->delete();
               }
          }

          if ($options->siropuEasyUserBanThread)
          {
               $threadBans = \XF::finder('Siropu\EasyUserBan:ThreadBan')
                    ->where('end_date', '>', 0)
                    ->where('end_date', '<', \XF::$time)
                    ->fetch();

               foreach ($threadBans as $threadBan)
               {
                    $threadBan->delete();
               }
          }
     }
}
 		  	   				  		 	 	  	  	  		 	  	   			   	  		 			 		 				  		 				   		 				 		  	 	 	   		  			   	 		 	  		 		  	 	 	  		  	 
