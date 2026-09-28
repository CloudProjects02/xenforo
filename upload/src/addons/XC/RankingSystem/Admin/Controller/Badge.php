<?php

namespace XC\RankingSystem\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Badge extends AbstractController {

    public function actionIndex() {

        $page = $this->filterPage();
        $perPage = 15;

        $tasklist = $this->Finder('XC\RankingSystem:Badge');

        $total = $tasklist->total();
        $this->assertValidPage($page, $perPage, $total, 'Badge');
        $tasklist->limitByPage($page, $perPage);

        $viewParams = [
            'badges' => $tasklist->order('display_order', 'ASC')->fetch(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total
        ];

        return $this->view('XC\RankingSystem:Badge', 'badge_list', $viewParams);
    }

//************************Add, Edit Function**********************************************



    public function actionEdit(ParameterBag $params) {
        $Badge = $this->assertBadgeExists($params->badge_id);

        return $this->badgeAddEdit($Badge);
    }

    protected function badgeAddEdit(\XC\RankingSystem\Entity\Badge $Badge) {

        $userCriteria = $this->app->criteria('XF:User', $Badge->user_criteria);

        $viewParams = [
            'Badge' => $Badge,
            'userCriteria' => $userCriteria,
            'nodeTree' => $this->getNodesRepo(),
        ];
        return $this->view('XC\RankingSystem:Badge', 'badge_edit', $viewParams);
    }

    public function actionAdd() {
        $Badge = $this->em()->create('XC\RankingSystem:Badge');
        return $this->badgeAddEdit($Badge);
    }

//************************Save category**********************************************
    protected function BadgeSaveProcess(\XC\RankingSystem\Entity\Badge $Badge) {

        $form = $this->formAction();

        $input = $this->filter([
            'title' => 'str',
            'description' => 'str',
            'display_order' => 'int',
            'user_criteria' => 'array',
        ]);
        



        if(!$input['title'] || !count($input['user_criteria'])){
            
             throw $this->exception(
                                $this->notFound(\XF::phrase("xc_required_field"))
                );
        }
       
        $BadgeName = $this->actionFind($input['title']);

        if (!$BadgeName) {
            $form->basicEntitySave($Badge, $input);

            return $form;
        } else {


            if ($Badge->badge_id == $BadgeName->badge_id) {
                $form->basicEntitySave($Badge, $input);

                return $form;
            } else {
                $phraseKey = $Badge->title . ' ' . \XF::phrase("xc_already_exit");
                throw $this->exception(
                                $this->notFound(\XF::phrase($phraseKey))
                );
            }
        }
    }

    public function actionSave(ParameterBag $params) {
        $this->assertPostOnly();

        if ($params->badge_id) {
            $Badge = $this->assertBadgeExists($params->badge_id);
        } else {
            
            
            $Badge = $this->em()->create('XC\RankingSystem:Badge');
            
                    $upload = $this->request->getFile('badge_svg', false, false);




                    if(!$upload){

                          throw $this->exception(
                                        $this->notFound(\XF::phrase("xc_required_image"))
                        );
                    }

                     $extension=$upload->getExtension();

                    if(!in_array($extension, ['jpg','png','svg','jpeg'])){

                                        throw $this->exception(
                                        $this->notFound(\XF::phrase("xc_svg_format"))
                        );
                    }
        }

        $this->BadgeSaveProcess($Badge)->run();

        $upload = $this->request->getFile('badge_svg', false, false);

        if ($upload) {

            $uploadService = $this->service('XC\RankingSystem:UploadSvg', $Badge);

            if (!$uploadService->setSvgFromUpload($upload)) {

                return $this->error($uploadService->getError());
            }



            if (!$uploadService->uploadSvg()) {
                return $this->error(\XF::phrase('Image Cannot be processed'));
            }
        }

        return $this->redirect($this->buildLink('badge'));
    }

//*********************************************************************************


    public function actionDelete(ParameterBag $params) {

        $Badge = $this->assertBadgeExists($params->badge_id);

        /** @var \XF\ControllerPlugin\Delete $plugin */
        $plugin = $this->plugin('XF:Delete');
        
        if($this->isPost() && $Badge->getimageExit()){
            
           $path=$Badge->getAbstractedCustomDepositSvgPath($Badge->file_ex);
           
           \XF\Util\File::deleteFromAbstractedPath($path);
        }

        return $plugin->actionDelete(
                        $Badge,
                        $this->buildLink('badge/delete', $Badge),
                        $this->buildLink('badge/edit', $Badge),
                        $this->buildLink('badge'),
                        $Badge->title
        );
    }

    protected function assertBadgeExists($id, $with = null, $phraseKey = null) {
        return $this->assertRecordExists('XC\RankingSystem:Badge', $id, $with, $phraseKey);
    }

    public function actionFind($title) {

        $task = $this->finder('XC\RankingSystem:Badge')->where('title', $title)->fetchOne();
        return $task;
    }
    
    protected function getNodesRepo() {
        /** @var \XF\Repository\Node $nodeRepo */
        $nodeRepo = \XF::repository('XF:Node');
        $nodeTree = $nodeRepo->createNodeTree($nodeRepo->getFullNodeList());

        return $nodeTree;
    }

}
