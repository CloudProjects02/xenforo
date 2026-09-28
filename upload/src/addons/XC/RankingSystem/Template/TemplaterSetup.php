<?php

namespace XC\RankingSystem\Template;

class TemplaterSetup {

   

    public static function userPointData(\XF\Template\Templater $templater, bool &$escape,$user) {

       
        
        

        if($user->user_id){
            
             $levelService = \xf::app()->service('XC\RankingSystem:Level');
       
            return  $levelService->caluPercentage($user);
        }
        
        
        
       
       
    }
    
    public static function userUnlockBadges(\XF\Template\Templater $templater, bool &$escape,$user) {

        if($user->user_id){
            
            $BadgeService = \xf::app()->service('XC\RankingSystem:Badge');
            
            return  $BadgeService->unAwardBadgeUser($user);
        }
        
        
    }

  

}
