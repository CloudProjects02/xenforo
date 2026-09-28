<?php

namespace XC\RankingSystem\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class AwardXp extends AbstractController {

    public function actionindex() {
        
       

        if ($this->isPost())
        {
            
            
            $userNames = $this->filter('usernames', 'str');
            
            $past_award_xp_id = $this->filter('past_award_xp_id', 'int');
            
            
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
            $exppoints=$this->filter('exppoints', 'array-uint');
        

            if(!count($exppoints)){
                
                 throw $this->exception(
                                $this->notFound(\XF::phrase("xc_select_required_exppoint"))
                );
                
            }
            
            if($past_award_xp_id){
                
                $generalService = $this->service('XC\RankingSystem:General');
                $awardXp=$generalService->checkAwardXp($past_award_xp_id);
                
                if(!in_array($awardXp->xp_id,$exppoints)){
                    
                    $awardXp->delete();
                }
                
            }
            
            $expoints = $this->finder('XC\RankingSystem:ExperiencePoint')
                ->where('xp_id', $this->filter('exppoints', 'array-uint'))
                ->fetch();
            
            
            $xpService = $this->service('XC\RankingSystem:ExperienctPoint');
            
            foreach ($users AS $user)
            {
                foreach ($expoints AS $expoint)
                {
                    $xpService->manuallyAwardXPToUser($expoint, $user);
                }
            }

            return $this->redirect($this->buildLink('award-xp/list'));
        }
        else
        {
           
            $xpService = $this->service('XC\RankingSystem:ExperienctPoint');
            $expPoints = $xpService->findExperiencePointForList()->fetch();

            if(!count($expPoints)){
                
                throw $this->exception(
                                $this->notFound(\XF::phrase("xc_add_required_experience_point"))
                );
            }
            
            $viewParams = [
                'expPoints'   => $expPoints,
               
            ];

            return $this->view('XC\RankingSystem:AwardXp', 'xc_award_experience_point', $viewParams);
        }
    }

    protected function assertawardUserAwardXPExists($id, $with = null, $phraseKey = null) {
        return $this->assertRecordExists('XC\RankingSystem:AwardXp', $id, $with, $phraseKey);
    }

     public function actionDelete(ParameterBag $params) {

        $awardXp = $this->assertawardUserAwardXPExists($params->award_xp_id);

        /** @var \XF\ControllerPlugin\Delete $plugin */
        
        if($this->isPost()){
            
             $generalService = $this->service('XC\RankingSystem:General');
             $generalService->reducePoint($awardXp->User,$awardXp->spot_ex_point,$awardXp,\xf::phrase('xc_manuall_remove_points'));
        }
        
        $plugin = $this->plugin('XF:Delete');

        return $plugin->actionDelete(
                        $awardXp,
                        $this->buildLink('award-xp/delete', $awardXp),
                        $this->buildLink('award-xp/edit', $awardXp),
                        $this->buildLink('award-xp/list'),
                        $awardXp->User->username
        );
    }
    

   public function actionEdit(ParameterBag $params) {
     
           $awardXp = $this->assertawardUserAwardXPExists($params->award_xp_id);

        
            
          
            $xpService = $this->service('XC\RankingSystem:ExperienctPoint');
            $expPoints = $xpService->findExperiencePointForList()->fetch();

            
            $viewParams = [
                'expPoints'   => $expPoints,
                'username' =>$awardXp->User->username,
                'awardXp' =>$awardXp
               
            ];

            return $this->view('XC\RankingSystem:AwardXp', 'xc_award_experience_point', $viewParams);
    }

     public function actionList(){
        
        $page = $this->filterPage();
        $perPage = 15;

        $awardlist = $this->Finder('XC\RankingSystem:AwardXp');

        $total = $awardlist->total();
        $this->assertValidPage($page, $perPage, $total, 'award-xp/list');
        $awardlist->limitByPage($page, $perPage);

     
        $viewParams = [
            'awardlist' => $awardlist->order('award_date','DESC')->fetch(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total
        ];

        return $this->view('XC\RankingSystem:AwardXp', 'xc_user_xp_award_list',$viewParams);
        
    }

}
