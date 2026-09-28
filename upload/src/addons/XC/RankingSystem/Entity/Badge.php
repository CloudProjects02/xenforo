<?php

namespace XC\RankingSystem\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

class Badge extends Entity {
    


    public static function getStructure(Structure $structure) {
        $structure->table = 'xc_badges';
        $structure->shortName = 'XC\RankingSystem:Badge';
        $structure->primaryKey = 'badge_id';
        $structure->contentType = 'award_badge';
        $structure->columns = [
            
            'badge_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'title' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'description' => ['type' => self::STR, 'default' => ''],
            'display_order' => ['type' => self::UINT, 'default' => 0],
            'user_criteria' => ['type' => self::JSON_ARRAY, 'default' => []],
            'file_ex' => ['type' => self::STR, 'default' => null]
        ];

       

        return $structure;
    }
    
     public function getAbstractedCustomDepositSvgPath($extension) {
       
         $badge_id = $this->badge_id;
        
        return sprintf('data://AwardeBadge/%d/%d.'.$extension, floor($badge_id / 1000), $badge_id);
        
    }
    
     public function getImgPath($canonical = true) {
         
         $badge_id = $this->badge_id;
         
         $file_ex = $this->file_ex;
       
         $path=sprintf('AwardeBadge/%d/%d.'.$file_ex, floor($badge_id / 1000), $badge_id);
      
        $path= \XF::app()->applyExternalDataUrl($path, $canonical);
        
        $path.="?".\xf::$time;
        
        return $path;
        
         
    }
     public function getimageExit(){
        
        $file_ex = $this->file_ex;
        
        $badge_id = $this->badge_id;
       
        $fileexit=sprintf('data://AwardeBadge/%d/%d.'.$file_ex, floor($badge_id / 1000), $badge_id);
        
        return \XF\Util\File::abstractedPathExists($fileexit);
    }

}
