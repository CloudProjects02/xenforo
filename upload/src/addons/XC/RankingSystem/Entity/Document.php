<?php

namespace XC\RankingSystem\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

class Document extends Entity {

    public static function getStructure(Structure $structure) {


        $structure->table = 'xc_level_document';
        $structure->shortName = 'XC\RankingSystem:Document';
        $structure->primaryKey = 'doc_id';
        $structure->columns = [
            'doc_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'doc_title' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'doc_ext' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
        ];

        return $structure;
    }
    
     public function getAbstractedCustomdocPath($extension) {

        $doc_id = $this->doc_id;

        return sprintf('data://ImportLevel/%d/%d.' . $extension, floor($doc_id / 1000), $doc_id);
    }
    
    public function viewAbstractedCustomdocPath() {

        $doc_id = $this->doc_id;
        $extension=$this->doc_ext;
        $path = sprintf('ImportLevel/%d/%d.' . $extension, floor($doc_id / 1000), $doc_id);

        return \XF::getRootDirectory() . '/data/' . $path;
    }
    
  
    
     public function abstractDocumentPath($canonical = true) {
         
        $doc_id = $this->doc_id;
        $extension=$this->doc_ext;
        $path = sprintf('ImportLevel/%d/%d.' . $extension, floor($doc_id / 1000), $doc_id);

        return \XF\Util\File::abstractedPathExists($fileexit);
         
    }

    

}
