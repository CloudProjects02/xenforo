<?php

namespace XC\RankingSystem\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\Util\Arr;

class ExperiencePoint extends AbstractController {

    public function actionIndex() {

        $page = $this->filterPage();
        $perPage = 15;

        $expPoints = $this->Finder('XC\RankingSystem:ExperiencePoint');

        $total = $expPoints->total();
        $this->assertValidPage($page, $perPage, $total, 'exp-point');
        $expPoints->limitByPage($page, $perPage);

        $viewParams = [
            'expPoints' => $expPoints->order('display_order', 'ASC')->fetch(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total
        ];

        return $this->view('XC\RankingSystem:ExperiencePoint', 'experience_point_list',$viewParams);
    }

//************************Add, Edit Function**********************************************

    public function PointAddEdit($expPoint) {
        $viewParams = [
            'expPoint' => $expPoint,
        ];

        return $this->view('XC\RankingSystem:ExperiencePoint', 'experience_point_edit', $viewParams);
    }

    public function actionEdit(ParameterBag $params) {
        

       
        $expPoint = $this->assertpointExists($params->xp_id);

        return $this->PointAddEdit($expPoint);
    }

    public function actionAdd() {
        
     
        $expPoint = $this->em()->create('XC\RankingSystem:ExperiencePoint');
        
         return $this->PointAddEdit($expPoint);
    }

//************************Save option**********************************************
    protected function PointSaveProcess(\XC\RankingSystem\Entity\ExperiencePoint $expPoint) {

        $form = $this->formAction();

        $input = $this->filter([
            
			'title' => 'str',
			'description' => 'str',
			'display_order' => 'int',
			'points' => 'int',
                        'point_type' => 'str',
                        'point_depend' => 'int'
			
		]);
        
   
       
       
       if($input['point_type']=='x_thread_replies' && !$input['point_depend']){
           
           throw $this->exception(
                                $this->notFound(\XF::phrase("xc_required_field"))
                );
           
       }
       if(!$input['title'] || !$input['points'] || !$input['point_type']){
            
             throw $this->exception(
                                $this->notFound(\XF::phrase("xc_required_field"))
                );
        }
      
        $pointName = $this->actionFindName($input['title']);
        
        if($expPoint->isInsert()){
        
            $pointTypeFount = $this->actionFindPointType($input['point_type']);

            if($pointTypeFount){

                $phraseKey = "Experience Point ".$pointTypeFount->title .' '. "already assigned....!";
                   throw $this->exception(
                                    $this->notFound(\XF::phrase($phraseKey))
                    );
            }
        }
        if (!$pointName) {
            $form->basicEntitySave($expPoint, $input);

            return $form;
        } else {
            

            if ($pointName->xp_id == $expPoint->xp_id) {
                $form->basicEntitySave($expPoint, $input);

                return $form;
            } else {
                $phraseKey = $expPoint->title .' '. \XF::phrase("xc_already_exit");
                throw $this->exception(
                                $this->notFound(\XF::phrase($phraseKey))
                );
            }
        }
    }

    public function actionSave(ParameterBag $params) {
        $this->assertPostOnly();

        if ($params->xp_id) {
            $expPoint = $this->assertpointExists($params->xp_id);
        } else {
            $expPoint = $this->em()->create('XC\RankingSystem:ExperiencePoint');
        }

        $this->PointSaveProcess($expPoint)->run();

        return $this->redirect($this->buildLink('exp-point'));
    }

//*********************************************************************************


    public function actionDelete(ParameterBag $params) {
        
       

        $expPoint = $this->assertpointExists($params->xp_id);

        /** @var \XF\ControllerPlugin\Delete $plugin */
        $plugin = $this->plugin('XF:Delete');

        return $plugin->actionDelete(
                        $expPoint,
                        $this->buildLink('exp-point/delete', $expPoint),
                        $this->buildLink('exp-point/edit', $expPoint),
                        $this->buildLink('exp-point'),
                        $expPoint->title
        );
    }

    protected function assertpointExists($id, $with = null, $phraseKey = null) {
        return $this->assertRecordExists('XC\RankingSystem:ExperiencePoint', $id, $with, $phraseKey);
    }

    public function actionFindName($title) {

       return  $this->finder('XC\RankingSystem:ExperiencePoint')->where('title', $title)->fetchOne();
       
    }
    
    public function actionFindPointType($point_type) {

       return  $this->finder('XC\RankingSystem:ExperiencePoint')->where('point_type', $point_type)->fetchOne();
       
    }
    
   
    
   


    

}
