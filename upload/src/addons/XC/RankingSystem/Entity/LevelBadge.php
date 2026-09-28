<?php

namespace XC\RankingSystem\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

class LevelBadge extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xc_level_badges';
        $structure->shortName = 'XC\RankingSystem:LevelBadge';
        $structure->primaryKey = 'level_badge_id';
        $structure->columns = [
            'level_badge_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'level_id' => ['type' => self::UINT, 'required' => true],
            'level' => ['type' => self::UINT, 'required' => true],
            'title' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'description' => ['type' => self::STR, 'default' => ''],
            'file_ex' => ['type' => self::STR, 'default' => 'svg'],
            'is_active' => ['type' => self::BOOL, 'default' => true],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time]
        ];

        $structure->relations = [
            'Level' => [
                'entity' => 'XC\RankingSystem:Levels',
                'type' => self::TO_ONE,
                'conditions' => 'level_id',
                'primary' => true
            ]
        ];

        return $structure;
    }

    public function getAbstractedCustomDepositSvgPath($extension = null)
    {
        $extension = $extension ?: $this->file_ex;
        $level_badge_id = $this->level_badge_id;
        
        return sprintf('data://LevelBadge/%d/%d.%s', floor($level_badge_id / 1000), $level_badge_id, $extension);
    }

    public function getImgPath($canonical = true)
    {
        $level_badge_id = $this->level_badge_id;
        $file_ex = $this->file_ex;
        
        // For SVG files, we need to serve them with proper MIME type
        if ($file_ex === 'svg') {
            $abstractedPath = $this->getAbstractedCustomDepositSvgPath();
            $fs = \XF::fs();
            
            if ($fs->has($abstractedPath)) {
                $fileContent = $fs->read($abstractedPath);
                // Create a data URL with proper MIME type
                return 'data:image/svg+xml;base64,' . base64_encode($fileContent);
            }
        }
        
        // For other file types, use the standard approach
        $path = sprintf('LevelBadge/%d/%d.%s', floor($level_badge_id / 1000), $level_badge_id, $file_ex);
        $path = \XF::app()->applyExternalDataUrl($path, $canonical);
        $path .= "?" . \XF::$time;
        
        return $path;
    }

    public function getImageExists()
    {
        $file_ex = $this->file_ex;
        $level_badge_id = $this->level_badge_id;
        
        $filePath = sprintf('data://LevelBadge/%d/%d.%s', floor($level_badge_id / 1000), $level_badge_id, $file_ex);
        
        return \XF\Util\File::abstractedPathExists($filePath);
    }

    protected function _preSave()
    {
        if ($this->isInsert() && !$this->created_date) {
            $this->created_date = \XF::$time;
        }
    }
}
