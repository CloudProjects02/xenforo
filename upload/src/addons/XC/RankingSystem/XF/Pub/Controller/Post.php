<?php

namespace XC\RankingSystem\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Post extends XFCP_Post {

    public function actionMarkSolution(ParameterBag $params) {


        $serviceGeneral = $this->service('XC\RankingSystem:General');

        $post = $this->assertViewablePost($params->post_id);

        $thread = $post->Thread;

        if (!$post->canMarkAsQuestionSolution($error)) {
            return $this->noPermission($error);
        }

        $thread = $post->Thread;
        $existingSolution = $thread->Question->Solution ?? null;

        if (!$existingSolution) {
            $type = 'add';
        } else if ($post->post_id == $existingSolution->post_id) {
            $type = 'remove';
        } else {
            $type = 'replace';
        }



        if ($serviceGeneral->isAllowForumsXp($thread->node_id) && $type == 'add') {


            $XpGeneral = $this->service('XC\RankingSystem:ExperienctPoint');

            $bestAnswerXp = $XpGeneral->checkXp("solve_answer");

            if ($bestAnswerXp) {


                $XpGeneral->AwardXPToUser($bestAnswerXp, $post->User, $post->post_id,true);
            }
        }

        if ($serviceGeneral->isAllowForumsXp($thread->node_id) && $type == 'remove') {


            $XpGeneral = $this->service('XC\RankingSystem:ExperienctPoint');

            $bestAnswerRemove = $XpGeneral->checkXpAwardUser("solve_answer", $post->post_id);

            if ($bestAnswerRemove) {

                $generalService = $this->service('XC\RankingSystem:General');
                $generalService->reducePoint($bestAnswerRemove->User,$bestAnswerRemove->spot_ex_point,$bestAnswerRemove,$bestAnswerRemove->Xp->title); 
                $bestAnswerRemove->delete();
            }
        }



        if ($serviceGeneral->isAllowForumsXp($thread->node_id) && $this->isPost() && $type == 'replace' && $this->filter('confirm', 'bool')) {

            $XpGeneral = $this->service('XC\RankingSystem:ExperienctPoint');

            $thread = $post->Thread;
            
            $typeData = $thread->type_data;

            $existingSolutionPostId = $typeData['solution_post_id'];
           
            $bestAnswerRemove = $XpGeneral->checkXpAwardUser("solve_answer", $existingSolutionPostId);

            if ($bestAnswerRemove) {

                 $generalService = $this->service('XC\RankingSystem:General');
                $generalService->reducePoint($bestAnswerRemove->User,$bestAnswerRemove->spot_ex_point,$bestAnswerRemove,$bestAnswerRemove->Xp->title); 
                
                $bestAnswerRemove->delete();
            }
            $bestAnswerXp = $XpGeneral->checkXp("solve_answer");

            if ($bestAnswerXp) {

                $XpGeneral->AwardXPToUser($bestAnswerXp, $post->User, $post->post_id,true);
            }
        }

        return parent::actionMarkSolution($params);
    }
    
    public function actionDelete(ParameterBag $params) {
       
        
        
        $post = $this->assertViewablePost($params->post_id);
       
        if($this->isPost()){
           
          
            if($post->isFirstPost()){
                
                $XpGeneral = $this->service('XC\RankingSystem:ExperienctPoint');
                $threadExit = $XpGeneral->checkXpAwardUser("create_thread", $post->Thread->thread_id);
               

                if($threadExit){
                  
                    $generalService = $this->service('XC\RankingSystem:General');
                    $generalService->reducePoint($post->Thread->User,$threadExit->spot_ex_point,$threadExit,$threadExit->Xp->title);
                    $threadExit->delete();
                    
                }
                
            }elseif(!$post->isFirstPost()){
                
                $XpGeneral = $this->service('XC\RankingSystem:ExperienctPoint');
                $postExit = $XpGeneral->checkXpAwardUser("create_post", $post->post_id);
                if($postExit){
                  
                    $generalService = $this->service('XC\RankingSystem:General');
                    $generalService->reducePoint($post->User,$postExit->spot_ex_point,$postExit,$postExit->Xp->title); 
                    $postExit->delete();
                }
                
            }
           
        }
        
        $parent= parent::actionDelete($params);
        return $parent;
    }

}
