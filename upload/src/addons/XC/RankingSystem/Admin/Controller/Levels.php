<?php

namespace XC\RankingSystem\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\Util\Arr;

class Levels extends AbstractController {

    
    public function actionIndex() {

        
        $page = $this->filterPage();
        $perPage = 20;

        $tasklist = $this->Finder('XC\RankingSystem:Levels');

        $total = $tasklist->total();
        $this->assertValidPage($page, $perPage, $total, 'levels');
        $tasklist->limitByPage($page, $perPage);

        $viewParams = [
            'levellist' => $tasklist->order('level', 'ASC')->fetch(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'doc' => $this->finder('XC\RankingSystem:Document')->fetchOne(),
        ];

        return $this->view('XC\RankingSystem:Levels', 'level_list', $viewParams);
    }
    
    public function actionEdit(ParameterBag $params) {
        

       $level = $this->assertlevelExists($params->level_id);

        return $this->levelAddEdit($level);
    }

    protected function levelAddEdit(\XC\RankingSystem\Entity\Levels $level) {

        // Load the level badge relation if it exists
        if (!$level->isInsert()) {
            $level->hydrateRelation('LevelBadge', $this->finder('XC\RankingSystem:LevelBadge')
                ->where('level_id', $level->level_id)
                ->fetchOne());
        }

        $viewParams = [
            'level' => $level,
         
           
        ];
        return $this->view('XC\RankingSystem:Levels', 'level_edit', $viewParams);
    }

    public function actionAdd() {
        
          $doc=$this->finder('XC\RankingSystem:Document')->fetchOne();
        
        if($doc){
            
            throw $this->exception(
                                $this->notFound(\XF::phrase("xc_file_importing"))
                );
        }
        $level = $this->em()->create('XC\RankingSystem:Levels');
        return $this->levelAddEdit($level);
    }

//************************Save category**********************************************
    protected function levelSaveProcess(\XC\RankingSystem\Entity\Levels $level) {
      
        $form = $this->formAction();

        $input = $this->filter([
            
			'level' => 'int',
			'exp_points' => 'int',
			
		]);

        // Handle badge image upload
        $badgeImage = $this->request->getFile('badge_image', false, false);
        
        if(!$input['level'] || !$input['exp_points']){
            
                throw $this->exception(
                                $this->notFound(\XF::phrase("xc_required_field"))
                );
        }
      
        $pointFound = $this->actionFindPoint($input['exp_points']);
        
        if($pointFound && $pointFound->level_id != $level->level_id){
            
            $phraseKey = "Point ".$pointFound->exp_points .' '. "already assing to level ".$pointFound->level;
               throw $this->exception(
                                $this->notFound(\XF::phrase($phraseKey))
                );
        }
        
        $levelFound = $this->actionFind($input['level']);

        if (!$levelFound) {
            $form->basicEntitySave($level, $input);

            // Create level badge after the form is processed
            $form->complete(function() use ($level, $badgeImage) {
                $levelBadge = $this->createLevelBadgeForLevel($level);

                // Handle badge image upload
                if ($badgeImage && $levelBadge) {
                    $this->uploadBadgeImage($levelBadge, $badgeImage);
                }
            });

            return $form;
        } else {
            

            if ($levelFound->level_id == $level->level_id) {
                $form->basicEntitySave($level, $input);

                // Update level badge after the form is processed
                $form->complete(function() use ($level, $badgeImage) {
                    $levelBadge = $this->updateLevelBadgeForLevel($level);

                    // Handle badge image upload
                    if ($badgeImage && $levelBadge) {
                        $this->uploadBadgeImage($levelBadge, $badgeImage);
                    }
                });

                return $form;
            } else {
                $phraseKey = "Level ".$levelFound->level .' '. \XF::phrase("xc_already_exit");
                throw $this->exception(
                                $this->notFound(\XF::phrase($phraseKey))
                );
            }
        }
    }

    public function actionSave(ParameterBag $params) {
        $this->assertPostOnly();

        if ($params->level_id) {
            $level = $this->assertlevelExists($params->level_id);
        } else {
            $level = $this->em()->create('XC\RankingSystem:Levels');
        }

        $this->levelSaveProcess($level)->run();

        return $this->redirect($this->buildLink('levels'));
    }

//*********************************************************************************


    public function actionDelete(ParameterBag $params) {

       $level = $this->assertlevelExists($params->level_id);

        /** @var \XF\ControllerPlugin\Delete $plugin */
        $plugin = $this->plugin('XF:Delete');

        return $plugin->actionDelete(
                        $level,
                        $this->buildLink('levels/delete', $level),
                        $this->buildLink('levels/edit', $level),
                        $this->buildLink('levels'),
                        $level->level
        );
    }

    protected function assertlevelExists($id, $with = null, $phraseKey = null) {
        return $this->assertRecordExists('XC\RankingSystem:Levels', $id, $with, $phraseKey);
    }

    public function actionFind($level) {

        $task = $this->finder('XC\RankingSystem:Levels')->where('level', $level)->fetchOne();
        return $task;
    }
    
    public function actionFindPoint($point) {

        $task = $this->finder('XC\RankingSystem:Levels')->where('exp_points', $point)->fetchOne();
        return $task;
    }

    protected function createLevelBadgeForLevel(\XC\RankingSystem\Entity\Levels $level)
    {
        // Check if level badge already exists
        $existingBadge = $this->finder('XC\RankingSystem:LevelBadge')
            ->where('level_id', $level->level_id)
            ->fetchOne();

        if ($existingBadge) {
            return $existingBadge;
        }

        // Create new level badge
        $levelBadge = $this->em()->create('XC\RankingSystem:LevelBadge');
        $levelBadge->level_id = $level->level_id;
        $levelBadge->level = $level->level;
        $levelBadge->title = $this->generateLevelTitle($level->level);
        $levelBadge->description = $this->generateLevelDescription($level->level, $level->exp_points);
        $levelBadge->file_ex = 'svg';
        $levelBadge->is_active = true;

        $levelBadge->save();

        return $levelBadge;
    }

    protected function updateLevelBadgeForLevel(\XC\RankingSystem\Entity\Levels $level)
    {
        $levelBadge = $this->finder('XC\RankingSystem:LevelBadge')
            ->where('level_id', $level->level_id)
            ->fetchOne();

        if ($levelBadge) {
            $levelBadge->level = $level->level;
            $levelBadge->title = $this->generateLevelTitle($level->level);
            $levelBadge->description = $this->generateLevelDescription($level->level, $level->exp_points);
            $levelBadge->save();

            return $levelBadge;
        }

        return $this->createLevelBadgeForLevel($level);
    }

    protected function generateLevelTitle($level)
    {
        $levelNames = [
            1 => 'Rookie',
            2 => 'Novice',
            3 => 'Apprentice',
            4 => 'Journeyman',
            5 => 'Expert',
            6 => 'Master',
            7 => 'Grandmaster',
            8 => 'Legend',
            9 => 'Mythic',
            10 => 'Divine'
        ];

        if (isset($levelNames[$level])) {
            return $levelNames[$level];
        }

        // For levels beyond 10, use a pattern
        if ($level <= 20) {
            return "Elite " . ($level - 10);
        } elseif ($level <= 50) {
            return "Champion " . ($level - 20);
        } elseif ($level <= 100) {
            return "Hero " . ($level - 50);
        } else {
            return "Level " . $level;
        }
    }

    protected function generateLevelDescription($level, $expPoints)
    {
        return "Achieved Level {$level} with {$expPoints} experience points";
    }

    protected function uploadBadgeImage(\XC\RankingSystem\Entity\LevelBadge $levelBadge, \XF\Http\Upload $upload)
    {
        if (!$upload->isValid()) {
            return false;
        }

        $extension = strtolower($upload->getExtension());
        $allowedExtensions = ['svg', 'png', 'jpg', 'jpeg', 'gif'];

        if (!in_array($extension, $allowedExtensions)) {
            return false;
        }

        // Update the file extension in the badge
        $levelBadge->file_ex = $extension;
        $levelBadge->save();

        // Get the abstracted path for the file
        $abstractedPath = $levelBadge->getAbstractedCustomDepositSvgPath($extension);

        // Write the file using XenForo's file system
        $fs = \XF::fs();
        $fileContent = file_get_contents($upload->getTempFile());
        
        // Delete existing file if it exists, then write the new one
        if ($fs->has($abstractedPath)) {
            $fs->delete($abstractedPath);
        }
        
        $fs->write($abstractedPath, $fileContent);

        return true;
    }
    
    
    public function actionimport(){
        
        $doc=$this->finder('XC\RankingSystem:Document')->fetchOne();
        
        if($doc){
            
            throw $this->exception(
                                $this->notFound(\XF::phrase("xc_file_importing"))
                );
        }
        
        return $this->view('XC\RankingSystem:Levels', 'import_level');
        
    }
    
    public function actiondoImport(){
        
        
        $uploadExcel = $this->request->getFile('csv_file', false, false);
        
       
        if($uploadExcel){
            
            $extension=$uploadExcel->getExtension();
            
  
           
            if(!in_array($extension, ['xlsx','xls','xlxs'])){
                
                                throw $this->exception(
                                $this->notFound(\XF::phrase("xc_excel_file_required"))
                );
            }
                                

                                
                $Document = $this->em()->create('XC\RankingSystem:Document');
                           
                $uploadService = $this->service('XC\RankingSystem:Upload', $Document);
                  
                if ($uploadExcel && !$uploadService->setDocFromUpload($uploadExcel)) {
                        return $this->error($uploadService->getError());
                    }
                if ($uploadExcel && !$uploadService->uploadDocument()) {
                    return $this->error($uploadService->getError());
                }
                
            $this->app()->jobManager()->enqueueUnique($Document->doc_id . '_excelfile', 'XC\RankingSystem:Document', ['doc_id' => $Document->doc_id], false);

        
        //    $this->app()->jobManager()->runUnique($Document->doc_id . '_excelfile', 20);
                
            }
            
            return $this->redirect($this->buildLink('levels'));
            
            
        }
         
    


    
    
    
}