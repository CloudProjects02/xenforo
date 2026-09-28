<?php

namespace XC\RankingSystem\ReputationSystem\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Reputation extends XFCP_Reputation
{
    public $pastReputation=null;
    
    protected function _preSave()
    {  
        $reputation = $this->finder('XFA\ReputationSystem:Reputation')
                        ->where('to_user_id', $this->to_user_id)
                        ->where('user_id', \XF::visitor()->user_id)
                        ->fetchOne();
        
        $this->pastReputation=$reputation;
       
        return parent::_preSave();
        
    }
    
    protected function _postSave()
    {
        

        
        if ($this->isUpdate() && ($this->rating==-1 || $this->rating==0  || $this->rating==-2)){
             
              $XpGeneral = \xf::app()->service('XC\RankingSystem:ExperienctPoint');
               $positiveReputationExit = $XpGeneral->checkXpAwardUser("positive_reputation", $this->reputation_id);
               
               if($positiveReputationExit){
                  
                   $generalService->reducePoint($positiveReputationExit->User,$positiveReputationExit->spot_ex_point,$positiveReputationExit,$positiveReputationExit->Xp->title);
                   $positiveReputationExit->delete();
               }
            
        }else{
            
            
                if($this->rating==2 || $this->rating==1){

                    $XpGeneral = \xf::app()->service('XC\RankingSystem:ExperienctPoint');

                    $positiveRep = $XpGeneral->checkXp("positive_reputation");


                    if($positiveRep && $this->pastReputation==null){

                        $XpGeneral->AwardXPToUser($positiveRep, $this->RatedUser, $this->reputation_id,true);
                    }
            }
        
        }
        
        
         return parent::_postSave();
    }
    
    protected function _postDelete()
    {
                $XpGeneral = \xf::app()->service('XC\RankingSystem:ExperienctPoint');
                $reputationExit = $XpGeneral->checkXpAwardUser("positive_reputation", $this->reputation_id);
                if($reputationExit){
                  
                    $generalService = \xf::app()->service('XC\RankingSystem:General');
                    $generalService->reducePoint($reputationExit->User,$reputationExit->spot_ex_point,$reputationExit,$reputationExit->Xp->title); 
                    $reputationExit->delete();
                }
     
        
        return parent::_postDelete();
    }
    
   
    
}