<?php
/**
 * XFDev - Lucky Awards
 * Give Users Award Randomly Based on Lucky Awards Configuration
 *
 * Created by @XFDev
 */

namespace XFDev\LuckyAwards\Repository;


class LuckyAward extends \XF\Mvc\Entity\Repository
{
    /**
     * Returns Finder for List of Lucky Awards
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function getLuckyAwardsList()
    {
        return $this->finder('XFDev\LuckyAwards:LuckyAward')
            ->with('Award')
            ->order('lucky_award_active', 'desc');
    }

    public function getLuckyAwardById($luckyAwardId)
    {
        return $this->finder('XFDev\LuckyAwards:LuckyAward')
            ->where('lucky_award_id','=',$luckyAwardId);
    }

    /**
     * Deletes Lucky Award based on Award Id also Delete Users Details of Lucky Award Based on Lucky Award Id
     *
     * @param $awardId
     * @throws \XF\PrintableException
     */
    public function deleteLuckyAwardByAwardIdWithUser($awardId)
    {
        $db = \XF::db();

        $db->beginTransaction();

        $luckyAwards = $this->finder('XFDev\LuckyAwards:LuckyAward')->where('award_id','=',$awardId)->fetch();

        foreach($luckyAwards as $luckyAward)
        {
            $db->delete('xfdev_users_lucky_award','lucky_award_id = ?',$luckyAward->lucky_award_id);
            $luckyAward->delete();
        }

        $db->commit();
    }


    /**
     * Checks if user already owns a lucky award
     *
     * @param $luckyAwardId
     * @param $userId
     * @return bool
     */
    public function checkUserOwnsLuckyAward($luckyAwardId,$userId)
    {
        $luckyAward = $this->finder('XFDev\LuckyAwards:UserLuckyAward')
            ->where('lucky_award_id','=',$luckyAwardId)
            ->where('user_id','=',$userId)
            ->fetchOne();

        if($luckyAward)
        {
            return true;
        }

        return false;
    }

    /**
     * Checks if user already owns an award
     *
     * @param $award_id
     * @param $user_id
     * @return bool
     */
    public function checkUserOwnsAward($award_id, $user_id)
    {
        $userAward = $this->finder('AddonFlare\AwardSystem:UserAward')
            ->where('award_id','=',$award_id)
            ->where('user_id','=',$user_id)->fetchOne();

        if($userAward)
        {
            return true;
        }

        return false;
    }

    /**
     * Gives User Lucky Award on Winning
     *
     * @param $luckyAward
     * @param $user_id
     * @param $postId
     * @return bool
     * @throws \XF\PrintableException
     */
    public function giveLuckyAwardToUser($luckyAward, $User,$postId)
    {
        $db = \XF::db();

        $db->beginTransaction();

        $userAward = $this->em->create('AddonFlare\AwardSystem:UserAward');
        $userAward->award_id = $luckyAward->award_id;
        $userAward->user_id = $User->user_id;
        $userAward->recommended_user_id = $User->user_id;
        $userAward->award_reason = $luckyAward->lucky_award_reason;
        $userAward->status = 'approved';
        $userAward->date_received = \XF::$time;
        $userAward->date_requested = \XF::$time;

        $save = $userAward->save();

        if($save)
        {
            $userAwardData = [
                'lucky_award_id'    =>  $luckyAward->lucky_award_id,
                'post_id'           =>  $postId,
                'user_id'           =>  $User->user_id,
                'date_received'     =>  \XF::$time
            ];

            $userSave = $this->db()->insert('xfdev_users_lucky_award',$userAwardData);

            $alertRepo = $this->repository('XF:UserAlert');

            $alertRepo->alertFromUser($User, $User, 'af_as_award', $luckyAward->award_id, 'award');
        }

        $db->commit();

        if($save && $userSave)
        {

            return true;
        }

        return false;

    }



}