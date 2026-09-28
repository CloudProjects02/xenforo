<?php
/**
 * XFDev - Lucky Awards
 * Give Users Award Randomly Based on Lucky Awards Configuration
 *
 * Created by @XFDev
 */

namespace XFDev\LuckyAwards\Entity;


use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null lucky_award_id
 * @property bool lucky_award_active
 * @property int award_id
 * @property int lucky_award_chance
 * @property array lucky_award_dependent
 * @property string lucky_award_reason
 *
 * GETTERS
 *
 *
 * RELATIONS
 * @property \AddonFlare\AwardSystem\Entity\Award Award
 */

/**
 * Class LuckyAward
 * @package XFDev\LuckyAwards\Entity
 */
class LuckyAward extends Entity
{

    /**
     * @return mixed|string|string[]|Entity|Entity[]|\XF\Mvc\Entity\FinderCollection|null
     */
    public function getAwardTitle()
    {
        return $this->Award->title;
    }

    /**
     * @return int
     */
    public function getTotalAwards()
    {
        return $this->getLuckyAwardRepo()->getLuckyAwardsList()->total();
    }

    /**
     * @param Structure $structure
     * @return void|Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xfdev_lucky_awards';
        $structure->shortName = 'XFDev\LuckyAwards:LuckyAward';
        $structure->primaryKey = 'lucky_award_id';
        $structure->columns = [
            'lucky_award_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'lucky_award_title' => ['type' => self::STR, 'default' => ''],
            'lucky_award_active' => ['type' => self::BOOL, 'default' => false],
            'award_id' => ['type' => self::UINT, 'default' => 0],
            'lucky_award_chances' => ['type' => self::UINT, 'default' => 0],
            'lucky_award_dependent' => ['type' => self::JSON_ARRAY, 'default' => []],
            'lucky_award_reason' => ['type' => self::STR, 'default' => '']
        ];
        $structure->relations = [
            'Award' => [
                'type' => self::TO_ONE,
                'entity' => 'AddonFlare\AwardSystem:Award',
                'conditions' => [
                    ['award_id', '=', '$award_id']
                ],
                'primary' => true
            ]
        ];
        $structure->getters = [
            'awardTitle' => true,
            'totalAwards' => true
        ];

        return $structure;

    }

    /**
     * @return \XFDev\LuckyAwards\Repository\LuckyAward
     */
    private function getLuckyAwardRepo()
    {
        return $this->repository('XFDev\LuckyAwards:LuckyAward');
    }
}