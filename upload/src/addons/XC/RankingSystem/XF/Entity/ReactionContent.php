<?php

namespace XC\RankingSystem\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class ReactionContent extends XFCP_ReactionContent {
    
    protected function _postSave() {
        
        
        $visitor=\XF::visitor();
        

       

        if ($this->isInsert() && $this->content_type=="post" && $visitor->user_id)
      
        {
                $post=$this->finder('XF:Post')->where('post_id',$this->content_id)->fetchOne();

                $nodeId=$post->Thread->node_id;
   
                $serviceGeneral = \xf::app()->service('XC\RankingSystem:General');

               if($serviceGeneral->isAllowForumsXp($nodeId)){

                        $XpGeneral = \xf::app()->service('XC\RankingSystem:ExperienctPoint');

                        $LikePostXp=$XpGeneral->checkXp("like_post");

                        if($LikePostXp){

                             $XpGeneral->AwardXPToUser($LikePostXp,$post->User,$post->post_id,true);
                         }
               }
            
            
        }
        return parent::_postSave();
    }
    
    protected function _postDelete()
	
        {
          $visitor=\XF::visitor();
         
        if ($this->content_type=="post" && $visitor->user_id)
      
        {
             $post=$this->finder('XF:Post')->where('post_id',$this->content_id)->fetchOne();

             $nodeId=$post->Thread->node_id;
   
                $serviceGeneral = \xf::app()->service('XC\RankingSystem:General');

               if($serviceGeneral->isAllowForumsXp($nodeId)){

                        $XpGeneral = \xf::app()->service('XC\RankingSystem:ExperienctPoint');

                        $postlikeExit = $XpGeneral->checkXpAwardUser("like_post", $post->post_id);
                       
                        if($postlikeExit){

                            $generalService =\xf::app()->service('XC\RankingSystem:General');
                            $generalService->reducePoint($postlikeExit->User,$postlikeExit->spot_ex_point,$postlikeExit,$postlikeExit->Xp->title); 
                            $postlikeExit->delete();
                        }
               }
             
         }
        
        
         return parent::_postSave();
    }
    
    
}