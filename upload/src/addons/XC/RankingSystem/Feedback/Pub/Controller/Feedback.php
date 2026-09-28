<?php

namespace XC\RankingSystem\Feedback\Pub\Controller;
use XF\Db\Exception;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;
class Feedback extends XFCP_Feedback
{
    
        public function actionDelete(ParameterBag $params)
            {

            
            if($this->isPost()){
                
                $XpGeneral = $this->service('XC\RankingSystem:ExperienctPoint');
                         $fb_id = $params->fb_id;

                                    $positiveFeedbackExit = $XpGeneral->checkXpAwardUser("positive_feedback", $fb_id);

                                    if($positiveFeedbackExit){

                                        $generalService = $this->service('XC\RankingSystem:General');
                                        $generalService->reducePoint($positiveFeedbackExit->User,$positiveFeedbackExit->spot_ex_point,$positiveFeedbackExit,$positiveFeedbackExit->Xp->title); 
                                        $positiveFeedbackExit->delete();
                                    }
            }
            
          return  parent::actionDelete($params);

         }
    
    
}
    
    