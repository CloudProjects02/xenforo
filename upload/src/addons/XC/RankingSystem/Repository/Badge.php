<?php

namespace XC\RankingSystem\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Badge extends Repository
{
    
       public function findBadgesForList()
	{
		return $this->finder('XC\RankingSystem:Badge');
	}
        
        public function updateBadgesForUser(\XF\Entity\User $user, $Badges = null)
	{
		

		$badgeService = \xf::app()->service('XC\RankingSystem:Badge');
		foreach ($Badges AS $badge)
		{

             
			$userCriteria = $this->app()->criteria('XF:User', $badge->user_criteria);
                        
                        $userCriteria->setMatchOnEmpty(false);
                      
			if ($userCriteria->isMatched($user))
			{ 
                              
				$badgeService->AwardbadageToUser($badge, $user);
			}
		}

		
	}
        
        
    public function maxThreadInCategories($nodeIds,$userId){
        
        
        $count=$this->finder('XF:Thread')->where('node_id',$nodeIds)->where('user_id',$userId)->fetch();
        
        return count($count) ? count($count) : 0 ;
    }
    
    
    public function positivefeedback($userId){
        
        $positiveFeedbackCount=\xf::db()->fetchAll("
			SELECT count(fb_id) AS count
			FROM xf_xc_feedback_feedback
			WHERE foruserid = ?
				AND amount = '1'
		", $userId);
        
        return $positiveFeedbackCount[0]['count'];
   
        
    }
    
      public function positiveReputation($userId){
        
        $positiveFeedbackCount=\xf::db()->fetchAll("
			SELECT count(reputation_id) AS count
			FROM xfa_rs_reputation
			WHERE to_user_id = ?
				AND (rating = '1' OR rating='2')
		", $userId);
        
        
        return $positiveFeedbackCount[0]['count'];
   
        
    }
    
    public function maxRepliesInCategories($nodeIds,$userId){
        
        $nodeIdsArray=implode("','",$nodeIds);
        $RepliesInCategorieCount=\xf::db()->fetchAll("SELECT count(xf_post.post_id) as count FROM xf_post LEFT JOIN xf_thread on xf_thread.thread_id=xf_post.thread_id WHERE xf_thread.node_id in ('".$nodeIdsArray."') AND xf_post.user_id!=$userId AND xf_thread.user_id=$userId");
        
        
        return $RepliesInCategorieCount[0]['count']; 
        
    }
    
     public function maxReachLevel($user){
         
          $pointlevel=$this->finder('XC\RankingSystem:Levels')->where('exp_points','>',$user->total_points)->order('exp_points','ASC')->fetchOne();

            
            if(!$pointlevel){
                  
                $lastLevel=$this->finder('XC\RankingSystem:Levels')->where('exp_points','<',$user->total_points)->order('exp_points','DESC')->fetchOne();
                
                return ["lastlevel",$lastLevel->level];
            }
            
          return ["nextlevel",$pointlevel->level];
     }
     
     public function maxSolveBestAnswer($userId){
         
         
         $SolveBestAnswerCount=\xf::db()->fetchAll("
			SELECT count(solution_user_id) AS count
			FROM xf_thread_question
			WHERE solution_user_id = ?
		", $userId);
        
         
        
        return $SolveBestAnswerCount[0]['count'];
         
     }
     
     public function maxReferMember($userId){
         
         
         $ReferMemberCount=\xf::db()->fetchAll("
			SELECT count(user_id) AS count
			FROM xf_user
			WHERE siropu_referrer_id = ?
		", $userId);
        
         
        
        return $ReferMemberCount[0]['count'];
     }
     
     public function maxReachPosts($userId){
     

         //$ReachPosts=\xf::db()->fetchAll("SELECT count(xf_post.post_id) as count FROM xf_post LEFT JOIN xf_thread on xf_thread.thread_id=xf_post.thread_id WHERE xf_thread.user_id=$userId");
        
          $posts=$this->finder('XF:Post')->where('user_id',$userId)->fetch();
          
         return count($posts); 
     }
     
     public function XThreadXReplies($threadcount,$repliesCount,$forumsBadges,$user){
         
         $threads=$this->finder('XF:Thread')->where('node_id',$forumsBadges)
                 ->where('user_id',$user->user_id)
                 ->order('reply_count','DESC')->pluckFrom('thread_id')->fetch($threadcount)->toArray();
        
        
         if(count($threads) && count($threads)>=(int)$threadcount){
              
           $posts=$this->finder('XF:Post')->where('thread_id',$threads)->where('user_id',$user->user_id)->fetch();
           
           if(count($posts) && count($posts)>=(int)$repliesCount){

               return true;
           }
           
         }
         
         return false;
         
     }
     
     public function LoginDailyStreak($user){
         
         if($user->first_visit && $user->latest_visit){
             
               $timeDiff=$user->latest_visit-$user->first_visit;
               
               if($timeDiff > 0){
                   
                   return ceil($timeDiff/86400);
               }
         }
     }
     
     public function ReachTotalPostLike($data,$user){
         
          $count=0;
          $posts=$this->finder('XF:Post')
                 ->where('user_id',$user->user_id)->pluckFrom('post_id')
                 ->fetch()->toArray();
       
          if(count($posts)){
             
              $likes=$this->finder('XF:ReactionContent')
                 ->where('content_id',$posts)->where('content_type','post')
                 ->fetch()->toArray();
            
              if(count($likes)){
                  
                  
                  return count($likes);
              }
              
          }
          
          return $count;
         
     }

    
}