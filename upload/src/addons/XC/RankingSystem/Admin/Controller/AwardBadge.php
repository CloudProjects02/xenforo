<?php

namespace XC\RankingSystem\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class AwardBadge extends AbstractController {

    public function actionindex() {
        
       
        if ($this->isPost()) {

           
            $userNames = $this->filter('usernames', 'str');
            if(!$userNames){
                
                throw $this->exception(
                                $this->notFound(\XF::phrase("select_username"))
                );
            }
            $userNames = preg_split('#\s*,\s*#', $userNames, -1, PREG_SPLIT_NO_EMPTY);

            $userRepo = $this->repository('XF:User');
            $users = $userRepo->getUsersByNames($userNames, $notFound, ['Privacy'])->toArray();

            if(count($notFound)){
                 
                throw $this->exception(
                                $this->notFound(\XF::phrase("xc_select_user_not_found",['users'=>implode(',',$notFound)]))
                );
                
            }
            $badgeIds=$this->filter('badges', 'array-uint');
            
            $updateBadgeId=$this->filter('update_badge', 'int');
            
            

            if(!count($badgeIds) && !$updateBadgeId){
                
                 throw $this->exception(
                                $this->notFound(\XF::phrase("xc_select_required_badge"))
                );
                
            }
            
            $past_award_badge_id = $this->filter('past_award_badge_id', 'int');
            
            if($past_award_badge_id){
                
                $generalService = $this->service('XC\RankingSystem:General');
                $awardBadge=$generalService->checkBadgeXp($past_award_badge_id);
                

                if($awardBadge->badge_id!=$updateBadgeId){
                    
                    $awardBadge->delete();
                }
                
            } 
            
            if($updateBadgeId){
                
                $badges=$updateBadgeId;
            }else{
                $badges=$badgeIds;
            }
        
            

            $badges = $this->finder('XC\RankingSystem:Badge')
                    ->where('badge_id', $badges)
                    ->fetch();
            $badgeService = $this->service('XC\RankingSystem:Badge');

            foreach ($users AS $user) {
                
                foreach ($badges AS $badge) {
                   
                    $badgeService->manuallyAwardbadageToUser($badge, $user);

                }
            }

            return $this->redirect($this->buildLink('award-badge/list'));
        } else {

            $badgeService = $this->service('XC\RankingSystem:Badge');
            $badges = $badgeService->findExperiencebadgeForList()->fetch();

             if(!count($badges)){
                
                throw $this->exception(
                                $this->notFound(\XF::phrase("xc_add_first_badge"))
                );
            }
            $viewParams = [
                'badges' => $badges,
            ];

            return $this->view('', 'xc_award_badge_user', $viewParams);
        }
    }

    protected function assertawardUserAwardBadgeExists($id, $with = null, $phraseKey = null) {
        return $this->assertRecordExists('XC\RankingSystem:AwardBadge', $id, $with, $phraseKey);
    }

     public function actionDelete(ParameterBag $params) {

        $awardBadge = $this->assertawardUserAwardBadgeExists($params->award_badge_id);

        /** @var \XF\ControllerPlugin\Delete $plugin */
        $plugin = $this->plugin('XF:Delete');

        return $plugin->actionDelete(
                        $awardBadge,
                        $this->buildLink('award-badge/delete', $awardBadge),
                        $this->buildLink('award-badge/edit', $awardBadge),
                        $this->buildLink('award-badge/list'),
                        $awardBadge->User->username
        );
    }
    

    public function actionEdit(ParameterBag $params) {
     
        $awardBadge = $this->assertawardUserAwardBadgeExists($params->award_badge_id);

        
            
           $badgeService = $this->service('XC\RankingSystem:Badge');
            $badges = $badgeService->findExperiencebadgeForList()->fetch();

            $viewParams = [
                'badges' => $badges,
                'awardBadge' => $awardBadge,
                'username' => $awardBadge->User->username
            ];

            return $this->view('', 'xc_award_badge_user', $viewParams);
    }

    public function actionList() {


        $page = $this->filterPage();
        $perPage = 15;

        $awardlist = $this->Finder('XC\RankingSystem:AwardBadge');    
        $total = $awardlist->total();
        $this->assertValidPage($page, $perPage, $total, 'award-badge/list');
        $awardlist->limitByPage($page, $perPage);

        
      
        $viewParams = [
            'awardlist' => $awardlist->order('award_date','DESC')->fetch(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total
        ];

        return $this->view('XC\RankingSystem:AwardBadge', 'xc_user_badge_award_list', $viewParams);
    }

}
