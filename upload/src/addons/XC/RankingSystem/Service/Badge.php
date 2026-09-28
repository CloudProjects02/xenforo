<?php

namespace XC\RankingSystem\Service;

class Badge extends \XF\Service\AbstractService {

    public function findExperiencebadgeForList() {
        
        return $this->finder('XC\RankingSystem:Badge')->order('display_order');
    }

    public function manuallyAwardbadageToUser(\XC\RankingSystem\Entity\Badge $Badge, \XF\Entity\User $user) {
       
        
        if($this->checkbatchExit($Badge->badge_id,$user->user_id)){
            
            throw new \XF\PrintableException(\XF::phrase("xc_badge_already_assign",['user'=>$user->username,'title'=>$Badge->title]));
            
        }

      
        $inserted = $this->db()->insert('xc_award_user_badge', [
            'user_id' => $user->user_id,
            'badge_id' => $Badge->badge_id,
            'award_date' => \XF::$time,
            'manual' => 1
                ], false, false, 'IGNORE');

         if ($inserted) {
   
            $alertRepo = $this->repository('XF:UserAlert');
            $alertRepo->alertFromUser(
                    $user,
                    $user,
                    'award_badge',
                    $Badge->badge_id,
                    'award'
            );

           

            return true;
        } else {
            return false;
        }
    }
    
     public function AwardbadageToUser(\XC\RankingSystem\Entity\Badge $Badge, \XF\Entity\User $user) {
       
        
        if($this->checkbatchExit($Badge->badge_id,$user->user_id)){
            
            
            return true;
            
        }

      
        $inserted = $this->db()->insert('xc_award_user_badge', [
            'user_id' => $user->user_id,
            'badge_id' => $Badge->badge_id,
            'award_date' => \XF::$time,
            'manual' => 0
                ], false, false, 'IGNORE');

        if ($inserted) {
   
            $alertRepo = $this->repository('XF:UserAlert');
            $alertRepo->alertFromUser(
                    $user,
                    $user,
                    'award_badge',
                    $Badge->badge_id,
                    'award'
            );

           

            return true;
        } else {
            return false;
        }
    }
    
    public function checkbatchExit($batchId,$userId){
        
        return $this->finder('XC\RankingSystem:AwardBadge')->where('user_id',$userId)->where('badge_id',$batchId)->fetchOne();
        
    }
    
    public function unAwardBadgeUser($user){
        
        $userBadges=$this->finder('XC\RankingSystem:AwardBadge')->where('user_id',$user->user_id)->pluckFrom('badge_id')->fetch()->toArray();
        
        $numberOfUnlockBadges=\xf::options()->xc_show_unlock_badges;
       
        $unlockBadges=null;
            
                $badgeRepo = \XF::repository('XC\RankingSystem:Badge');
                
                if($numberOfUnlockBadges){
                    		
                    $unlockBadges = $badgeRepo->findBadgesForList()->where('badge_id','!=',$userBadges)->fetch($numberOfUnlockBadges);
                    
                }

        
        return $unlockBadges;
    }

}
