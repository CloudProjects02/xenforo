<?php

namespace XC\RankingSystem\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Forum extends XFCP_Forum
{

    
    public function finalizeThreadCreate(\XF\Service\Thread\Creator $creator) {
        
        $parent=parent::finalizeThreadCreate($creator);
        
        
        $serviceGeneral = $this->service('XC\RankingSystem:General');
        
        $thread = $creator->getThread();
         
        
        if($serviceGeneral->isAllowForumsXp($thread->node_id)){
            
            $XpGeneral = $this->service('XC\RankingSystem:ExperienctPoint');
            
            $ThreadXp=$XpGeneral->checkXp("create_thread");
            
             $visitor=\xf::visitor();
           
             if($ThreadXp && $visitor->user_id){
                
                $XpGeneral->AwardXPToUser($ThreadXp,$visitor,$thread->thread_id,true);
            }
            
        }
        
      return $parent;
    }
    
    
}