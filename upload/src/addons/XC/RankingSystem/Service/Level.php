<?php

namespace XC\RankingSystem\Service;

require __DIR__ . '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Level extends \XF\Service\AbstractService {

    public function getSheetData($filePath, $detail = null) {



        $inputFileName = $filePath;

        $inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($filePath);

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);

        $spreadsheet = $reader->load($inputFileName);

        $array = $spreadsheet->getActiveSheet()->toArray();

        return $array;
    }
    
    public function insertLevel($level,$points){
        
        $exit=$this->finder('XC\RankingSystem:Levels')->where('level',$level)->fetchOne();
        
        if($exit){
            
            $exit->level=$level;
            $exit->exp_points=$points;
            $exit->save();
            
        }else{
            
            $levelRank=$this->em()->create('XC\RankingSystem:Levels');
            $levelRank->level=$level;
            $levelRank->exp_points=$points;
            $levelRank->save();
        }
        
        
    }
    
    public function caluPercentage($user){
        
        $data=[];
        
      if($user->total_points){
          
            $pointlevel=$this->finder('XC\RankingSystem:Levels')->where('exp_points','>',$user->total_points)->order('exp_points','ASC')->fetchOne();

            if(!$pointlevel){
                
                 $pointlevel=$this->finder('XC\RankingSystem:Levels')->where('exp_points','=',$user->total_points)->order('exp_points','ASC')->fetchOne();

            }

            if(!$pointlevel){
                
                $lastLevel=$this->finder('XC\RankingSystem:Levels')->where('exp_points','<',$user->total_points)->order('exp_points','DESC')->fetchOne();
               
                if(!$lastLevel){
                    
                     $lastLevel=$this->finder('XC\RankingSystem:Levels')->where('exp_points','=',$user->total_points)->order('exp_points','DESC')->fetchOne();
               
                }
                if($lastLevel->level){
               
                        $data['perc']=100;
                        $data['next_level']=$lastLevel->level;
                        $data['next_level_points']=$lastLevel->exp_points-$user->total_points < 0 || $lastLevel->exp_points-$user->total_points==0 ? null : $lastLevel->exp_points-$user->total_points;

                        return $data;
                }
            }
            
            if($pointlevel && $pointlevel->level){

                $percentage=($user->total_points /$pointlevel->exp_points)*100;
                $data['perc']=(int)ceil($percentage);
                $data['next_level']=$pointlevel->level;
                $data['next_level_points']=$pointlevel->exp_points-$user->total_points < 0 || $pointlevel->exp_points-$user->total_points==0 ? null :$pointlevel->exp_points-$user->total_points;

                return $data;

            }
            
      }elseif(!$user->total_points){
          
        $pointlevel=$this->finder('XC\RankingSystem:Levels')->where('exp_points','>',$user->total_points)->order('exp_points','ASC')->fetchOne(); 
        
        if($pointlevel && $pointlevel->level){
            
         
                $data['perc']=0;
                $data['next_level']=$pointlevel->level;
                $data['next_level_points']=$pointlevel->exp_points;
                
                return $data;
         
        }
         
       
      }
       

    }

}
