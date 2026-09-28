<?php

namespace XC\RankingSystem\Service;


use XC\RankingSystem\Entity\Document;
use XF\Entity\User;
class Upload extends \XF\Service\AbstractService 
{

    protected $doc;
    protected $error = null;
    protected $filepath;
     protected $filename;
    protected $extension;
    protected $throwErrors = true; 

    public function __construct(\XF\App $app, Document $document) {
        parent::__construct($app);
        $this->setdoc($document);
    }

    protected function setdoc(Document $document) {
       

        $this->doc = $document;
    }

    public function getError() {
        return $this->error;
    }

    public function setDocFromUpload($upload) {


         $this->filepath = $upload->getTempFile();
         $this->extension=$upload->getExtension();
         $this->filename=$upload->getFileName();

         return true;

    }



    public function uploadDocument() 
    {
        
       

        if (!$this->filepath) {
            return $this->throwException(new \LogicException("No source file for image set"));
        }
        

        $this->doc->doc_title=$this->filename;
        $this->doc->doc_ext=$this->extension;
        $this->doc->save();

        $dataFile = $this->doc->getAbstractedCustomdocPath($this->extension);
        \XF\Util\File::copyFileToAbstractedPath($this->filepath, $dataFile);
        
        
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