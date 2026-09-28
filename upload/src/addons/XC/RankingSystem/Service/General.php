<?php

namespace XC\RankingSystem\Service;

class General extends \XF\Service\AbstractService {

    public function isAllowForumsXp($nodeId) {

        $xc_forum_xp = \XF::options()->xc_forum_xp;

        if (isset($xc_forum_xp) && count($xc_forum_xp)) {

            $forumsXp = array_filter($xc_forum_xp);

            if (in_array($nodeId, $forumsXp)) {



                return true;
            }

            return false;
        }

        return false;
    }

    public function checkAwardXp($id) {

        return $this->finder('XC\RankingSystem:AwardXp')->where('award_xp_id', $id)->fetchOne();
    }

    public function checkBadgeXp($id) {

        return $this->finder('XC\RankingSystem:AwardBadge')->where('award_badge_id', $id)->fetchOne();
    }

    public function reducePoint($user, $minusPoint,$awardXP,$message) {

        if ($user->total_points) {

            $leftPoint = $user->total_points - $minusPoint;
            if ($leftPoint < 0) {
                $user->fastUpdate('total_points', 0);
            } else {
                $user->fastUpdate('total_points', $leftPoint);
            }
           
            if(\xf::options()->xc_alert_onoff){
                
                $alertRepo = $this->repository('XF:UserAlert');
                $alertRepo->alertFromUser(
                        $user,
                        $user,
                        'experience_point',
                         $awardXP->Xp->xp_id,
                        'remove',['message'=>$message,'minuspoint'=>$minusPoint]
                );
            
            }
        }
    }
    
    public function checkdayAgo($lastActivity){
        
        $dif = \xf::$time - $lastActivity;


        if($dif > 86400)
        {
        
            return true;
        }
        
        return false;
    }
    
   

}
