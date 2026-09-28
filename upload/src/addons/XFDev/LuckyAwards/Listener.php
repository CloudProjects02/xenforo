<?php
/**
 * XFDev - Lucky Awards
 * Give Users Award Randomly Based on Lucky Awards Configuration
 *
 * Created by @XFDev
 */

namespace XFDev\LuckyAwards;


class Listener
{

    /**
     * When an award is deleted, delete lucky award and lucky award winners associated with that award
     *
     * @param \XF\Mvc\Entity\Entity $entity
     */
    public static function entityAwardDelete(\XF\Mvc\Entity\Entity $entity)
    {
        $award_id = $entity->award_id;

        \XF::repository('XFDev\LuckyAwards:LuckyAward')->deleteLuckyAwardByAwardIdWithUser($award_id);
    }

    /**
     * When a user is deleted, delete his lucky award wining records
     *
     * @param \XF\Service\User\DeleteCleanUp $deleteService
     * @param array $deletes
     */
    public static function userDeleteCleanInit(\XF\Service\User\DeleteCleanUp $deleteService, array &$deletes)
    {
        $deletes['xfdev_users_lucky_award'] = 'user_id = ?';
    }
}