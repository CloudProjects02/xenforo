<?php

namespace XFRM\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $resource_download_id
 * @property int $resource_version_id
 * @property int $user_id
 * @property int $resource_id
 * @property int $last_download_date
 *
 * RELATIONS
 * @property \XFRM\Entity\ResourceItem $Resource
 * @property \XFRM\Entity\ResourceVersion $Version
 * @property \XF\Entity\User $User
 * 
 * GETTERS
 * @property int $total_downloads
 */
class ResourceDownload extends Entity
{
    public function getTotalDownloads()
    {
        return $this->db()->fetchOne("
            SELECT COUNT(*)
            FROM xf_rm_resource_download
            WHERE user_id = ?
        ", $this->user_id);
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_rm_resource_download';
        $structure->shortName = 'XFRM:ResourceDownload';
        $structure->primaryKey = 'resource_download_id';
        $structure->columns = [
            'resource_download_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'resource_version_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'resource_id' => ['type' => self::UINT, 'required' => true],
            'last_download_date' => ['type' => self::UINT, 'default' => \XF::$time]
        ];
        $structure->getters = [
            'total_downloads' => true
        ];
        $structure->relations = [
            'Resource' => [
                'entity' => 'XFRM:ResourceItem',
                'type' => self::TO_ONE,
                'conditions' => 'resource_id',
                'primary' => true
            ],
            'Version' => [
                'entity' => 'XFRM:ResourceVersion',
                'type' => self::TO_ONE,
                'conditions' => 'resource_version_id',
                'primary' => true
            ],
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
        ];

        return $structure;
    }
}