<?php

namespace XC\RankingSystem\Service;

use XC\RankingSystem\Entity\Badge;
use XF\Entity\User;

class UploadSvg extends \XF\Service\AbstractService 
{

    protected $badge;
    protected $error = null;
    protected $fileName;
    protected $extension;
    protected $throwErrors = true; 

    public function __construct(\XF\App $app, Badge $AwardBadge) {
        parent::__construct($app);
        $this->setBadge($AwardBadge);
    }

    protected function setBadge(Badge $AwardBadge) {
       

        $this->badge = $AwardBadge;
        
    }

    public function getError() {
        return $this->error;
    }

    public function setSvgFromUpload($upload) {


         $this->fileName = $upload->getTempFile();
         $this->extension=$upload->getExtension();
         

         return true;
       
    }



    public function uploadSvg() 
    {
        
     
            $this->badge->fastUpdate('file_ex',$this->extension);

            $dataFile = $this->badge->getAbstractedCustomDepositSvgPath($this->extension);
            \XF\Util\File::copyFileToAbstractedPath($this->fileName, $dataFile);
        
            
         
        return true;
    }
    
    protected function throwException(\Exception $error)
    {
        if ($this->throwErrors)
        {
            throw $error;
        }
        else
        {
            return false;
        }
    }
}