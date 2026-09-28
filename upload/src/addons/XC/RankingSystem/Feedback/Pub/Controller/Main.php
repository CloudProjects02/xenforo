<?php

namespace XC\RankingSystem\Feedback\Pub\Controller;
use XF\Db\Exception;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;
class Main extends XFCP_Main
{
  
    public function actionDoAddFeedback(parameterBag $params)
    { 
        $this->assertPostOnly();

//        if ( ! \XF::visitor()->canUseFeedbackXcfs() ||  !\XF::visitor()->feedbackRestrictedXcfs(\XF::visitor()) ) {
//            return $this->error(\XF::phrase('xcfs_cannot_view_the_page'));
//        }
//
//        if ( ! \XF::visitor()->canGiveFeedbackXcfs() ||  !\XF::visitor()->feedbackRestrictedXcfs(\XF::visitor())) {
//            return $this->error(\XF::phrase('xcfs_cannot_view_the_page'));
//        }

	    $fb_id = $this->filter('fb_id', 'uint');
         
           
	    if ( $fb_id ) {
                
                $input = $this->filter(array(
                    'type' => 'str',
                    'amount' => 'int',
                    'dealurl' => 'str',
                    'review' => 'str',
                    'comment_html' => 'str'
                ));
                
                  if($fb_id && ($input['amount']==-1 || $input['amount']==-2)){
           
                                    $XpGeneral = $this->service('XC\RankingSystem:ExperienctPoint');


                                    $positiveFeedbackExit = $XpGeneral->checkXpAwardUser("positive_feedback", $fb_id);

                                    if($positiveFeedbackExit){

                                        $generalService = $this->service('XC\RankingSystem:General');
                                        $generalService->reducePoint($positiveFeedbackExit->User,$positiveFeedbackExit->spot_ex_point,$positiveFeedbackExit,$positiveFeedbackExit->Xp->title); 
                                        $positiveFeedbackExit->delete();
                                    }

                 }
		    return $this->rerouteController('XenCentral\Feedback:Feedback', 'do-edit-feedback', $fb_id);
	    }

        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));

        $input = $this->filter(array(
            'foruserid' => 'int',
            'type' => 'str',
            'amount' => 'int',
            'dealurl' => 'str',
            'review' => 'str',
            'thread_id' => 'int',
            'comment_html' => 'str'
        ));
        

        $userRepo = \XF::repository('XF:User');
        $with = [];
        $user = \XF::em()->find('XF:User', $input['foruserid'], $with);

        $pastFeedback=$this->finder('XenCentral\Feedback:Feedback')->where('foruserid',$input['foruserid'])
                ->where('fromuserid', \XF::visitor()->user_id)->fetchOne();
        


        $feedbackWriter = $this->em()->create('XenCentral\Feedback:Feedback');
        $feedbackWriter->set('foruserid', $input['foruserid']);
        $feedbackWriter->set('fromuserid', \XF::visitor()->user_id);
        $feedbackWriter->set('amount', $input['amount']);
        $feedbackWriter->set('type', $input['type']);
        $feedbackWriter->set('threadid', $input['thread_id']);
        $feedbackWriter->set('dealurl', $input['dealurl']);
        $feedbackWriter->set('review', htmlspecialchars($input['review']));

        if (!$input['comment_html'] && $this->_getOptionsModel()->getRequireComment()) {
             {
                return $this->error(\XF::phrase('xcfs_please_enter_comment_text'));
            }
        }

	    if ( $input['comment_html'] && $this->_getOptionsModel()->getCommentMinimumLength() && strlen( strip_tags( $input['comment_html'] ) ) < $this->_getOptionsModel()->getCommentMinimumLength() ) {
		    $feedbackWriter->error(\XF::phrase( 'xcfs_comment_length_x', array(
			    'length' => $this->_getOptionsModel()->getCommentMinimumLength()
		    ) ) );
	    }

	$feedbackWriter->preSave();
        $feedbackWriter->save();

	$fb_id = $feedbackWriter->get('fb_id');

        $message = $this->plugin('XF:Editor')->fromInput('comment');

        if ($message) {
            $commentWriter = $this->em()->create('XenCentral\Feedback:FeedbackComment');
            $commentWriter->set('fb_id', $fb_id);
            $commentWriter->set('user_id', \XF::visitor()->user_id);
            $commentWriter->set('message', $message);
            $commentWriter->preSave();
            $commentWriter->save();
        }
        $activityRepo = \XF::repository('XenCentral\Feedback:Activity');
        $activityRepo->addedFeedback($fb_id, $user);

        if($fb_id && $input['amount']==1){
            
               $XpGeneral = $this->service('XC\RankingSystem:ExperienctPoint');

               $positiveFeedbackXp = $XpGeneral->checkXp("positive_feedback");
               

               if ($positiveFeedbackXp && $input['foruserid'] && !$pastFeedback) {

                $XpGeneral->AwardXPToUser($positiveFeedbackXp, $user,$fb_id,true);
            }
        }
        return $this->redirect($this->buildLink('feedback', $user));
    }
}
