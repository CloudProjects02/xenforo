<?php
/**
 * XFDev - Lucky Awards
 * Give Users Award Randomly Based on Lucky Awards Configuration
 *
 * Created by @XFDev
 */

namespace XFDev\LuckyAwards\Repository;


use XF\Mvc\Entity\Manager;

class UserLuckyAward extends \XF\Mvc\Entity\Repository
{
    /**
     * UserLuckyAward constructor.
     * @param Manager $em
     * @param $identifier
     */
    public function __construct(Manager $em, $identifier)
    {
        parent::__construct($em, $identifier);
    }

    /**
     * @return \XF\Mvc\Entity\Finder
     */
    public function findUserLuckyAwardsForList()
    {
        return $this->finder('XFDev\LuckyAwards:UserLuckyAward');
    }

    /**
     * Checks if user owns the dependent lucky awards to receive an lucky award
     *
     * @param $luckyAward
     * @param $userId
     */
    public function checkUserOwnsDependentLuckyAwards($luckyAward, $userId)
    {
        $dependentAwardsList = $luckyAward->lucky_award_dependent;

        $countDependent = count($dependentAwardsList);

        if ($countDependent > 0) {
            $db = \XF::db();

            $dependentCheck = $db->query("SELECT user_id from xfdev_users_lucky_award WHERE lucky_award_id IN (" . $db->quote($dependentAwardsList) . ")
                AND user_id = ? GROUP BY user_id HAVING COUNT(*) = ?",
                [
                    $userId,
                    $countDependent
                ]);

            if ($haveAwards = $dependentCheck->fetch()) {
                return true;
            } else {
                return false;
            }

        } else {
            return true;
        }

    }
}