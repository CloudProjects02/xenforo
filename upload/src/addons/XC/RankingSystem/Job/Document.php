<?php

namespace XC\RankingSystem\Job;


use XF\Job\AbstractJob;

use XF\Mvc\Entity\Finder;
use XF\Mvc\ParameterBag;
use XF\Http\Response;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use XF\Mvc\FormAction;
use XF\Mvc\View;

class Document extends AbstractJob {

    

    public function run($maxRunTime) {
    


       
       
        
        if($this->data['doc_id']){
        
            $document=\xf::app()->finder('XC\RankingSystem:Document')->where('doc_id',$this->data['doc_id'])->fetchOne();
 
            $filePath=$document->viewAbstractedCustomdocPath();
            
            $jobdone=false;
           
            if(file_exists($filePath)){
                
               $levelService = \xf::app()->service('XC\RankingSystem:Level');
               
                $sheetRecords=$levelService->getSheetData($filePath,null);
              
               
                if(count($sheetRecords)>0){
                    
                   
                       for ($i = 1; $i <= count($sheetRecords); $i++) {

                          
                               if(isset($sheetRecords[$i][0]) && isset($sheetRecords[$i][0])){
                               
                              
                                   $levelService->insertLevel((int) $sheetRecords[$i][0],(int) $sheetRecords[$i][1]);
                                
                               }else{
                                   
                                   continue;
                               }
                         
                             
                           }
                           
                        $jobdone=true;
                           
                       }
                   
                }
                
               
            
        }
        
    
        
        
     ///   if ($jobdone) {
            
             $document=\xf::app()->finder('XC\RankingSystem:Document')->where('doc_id',$this->data['doc_id'])->fetchOne();
 
             $filePath=$document->viewAbstractedCustomdocPath();
             
             if(file_exists($filePath)){
                 
                 unlink($filePath);
             }
             
             $document->delete();
         
            return $this->complete();
      //  }

        return $this->resume();

        
    }
    
    public function writelevel(){
        
        
    }
    
    
     public function getStatusMessage() {
        return \XF::phrase('processing_export_acess_log...');
    }

    public function canCancel() {
        return false;
    }

    public function canTriggerByChoice() {
        return false;
    }
     
    
 

}

