<?php

namespace Jace\TokenDownloads\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $purchase_id
 * @property int $user_id
 * @property int $package_id
 * @property int $purchase_date
 * @property int $tokens_total
 * @property int $tokens_remaining
 * @property int|null $expire_date
 * 
 * RELATIONS
 * @property \XF\Entity\User $User
 * @property \Jace\TokenDownloads\Entity\Package $Package
 */
class Purchase extends Entity
{
    public function canUseTokens()
    {
        return $this->tokens_remaining > 0 && ($this->expire_date === null || $this->expire_date > \XF::$time);
    }
    
    public function useToken()
    {
        if (!$this->canUseTokens())
        {
            return false;
        }
        
        $this->tokens_remaining--;
        return true;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'jace_token_purchase';
        $structure->shortName = 'Jace\TokenDownloads:Purchase';
        $structure->primaryKey = 'purchase_id';
        $structure->columns = [
            'purchase_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'package_id' => ['type' => self::UINT, 'required' => true],
            'purchase_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'tokens_total' => ['type' => self::UINT, 'required' => true, 'min' => 1],
            'tokens_remaining' => ['type' => self::UINT, 'required' => true, 'min' => 0],
            'expire_date' => ['type' => self::UINT, 'nullable' => true]
        ];
        $structure->getters = [];
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'Package' => [
                'entity' => 'Jace\TokenDownloads:Package',
                'type' => self::TO_ONE,
                'conditions' => 'package_id',
                'primary' => true
            ]
        ];

        return $structure;
    }
} 