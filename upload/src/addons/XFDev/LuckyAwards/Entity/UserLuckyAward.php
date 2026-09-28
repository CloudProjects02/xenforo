<?php
/**
 * XFDev - Lucky Awards
 * Give Users Award Randomly Based on Lucky Awards Configuration
 *
 * Created by @XFDev
 */

namespace XFDev\LuckyAwards\Entity;


use XF\Mvc\Entity\Structure;

/**
 * Class UserLuckyAward
 * @package XFDev\LuckyAwards\Entity
 */
class UserLuckyAward extends \XF\Mvc\Entity\Entity
{
    /**
     * @param Structure $structure
     * @return void|Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xfdev_users_lucky_award';
        $structure->shortName = 'XFDev\LuckyAwards:UserLuckyAward';
        $structure->primaryKey = 'user_lucky_award_id';
        $structure->columns = [
            'user_lucky_award_id' =>  ['type' =>  self::UINT, 'autoincrement' => true, 'nullable' => true],
            'lucky_award_id'    =>  ['type' =>  self::UINT, 'default' => 0],
            'post_id'           =>  ['type' =>  self::UINT, 'default' => 0],
            'user_id'           =>  ['type' =>  self::UINT, 'default' => 0],
            'date_received'     =>  ['type' =>  self::UINT, 'default' => 0]
        ];
        $structure->relations = [
            'User' => [
                'entity' 		=> 'XF:User',
                'type' 			=> self::TO_ONE,
                'conditions' 	=> [
                    ['user_id', '=', '$user_id']
                ],
                'primary'	=> true
            ],
            'LuckyAward' => [
                'entity' 		=> 'XFDev\LuckyAwards:LuckyAward',
                'type' 			=> self::TO_ONE,
                'conditions' 	=> [
                    ['lucky_award_id', '=', '$lucky_award_id']
                ],
                'primary'	=> true
            ],
            'Post' => [
                'entity' 		=> 'XF:Post',
                'type' 			=> self::TO_ONE,
                'conditions' 	=> [
                    ['post_id', '=', '$post_id']
                ],
                'primary'	=> true
            ]
        ];

        return $structure;
    }
}