<?php
/**
 * XFDev - Lucky Awards
 * Give Users Award Randomly Based on Lucky Awards Configuration
 *
 * Created by @XFDev
 */

namespace XFDev\LuckyAwards\XF\Service\Post;


class Preparer extends XFCP_Preparer
{
    public function afterInsert()
    {
        $User = \XF::visitor();

        $userId = $User->user_id;

        $excludedForums = \XF::options()->xfdev_lucky_awards_exclude_forums;

        if (!empty($excludedForums) && !in_array($this->post->Thread->Forum->node_id, $excludedForums)) {
            /**
             * @var XFDev\LuckyAwards\Repository\LuckyAward $luckyAwardRepo
             */
            $luckyAwardsRepo = $this->getLuckyAwardRepo();
            $userLuckyAwardsRepo = $this->getUserLuckyAwardRepo();

            $luckyAwards = $luckyAwardsRepo->getLuckyAwardsList();

            if (!empty($luckyAwards)) {
                foreach ($luckyAwards as $luckyAward) {
                    if ($luckyAward->lucky_award_active && rand(1, $luckyAward->lucky_award_chances) == rand(1, $luckyAward->lucky_award_chances)) {
                        if (!$luckyAwardsRepo->checkUserOwnsLuckyAward($luckyAward->lucky_award_id, $userId)
                            && !$luckyAwardsRepo->checkUserOwnsAward($luckyAward->award_id, $userId)
                            && $userLuckyAwardsRepo->checkUserOwnsDependentLuckyAwards($luckyAward,$userId)
                        )
                        {
                            if($luckyAwardsRepo->giveLuckyAwardToUser($luckyAward,$User,$this->post->post_id))
                            {
                                break;
                            }
                        }
                    }
                }
            }
        }
        parent::afterInsert();
    }

    /**
     * @return \XFDev\LuckyAwards\Repository\LuckyAward
     */
    private function getLuckyAwardRepo()
    {
        return $this->repository('XFDev\LuckyAwards:LuckyAward');
    }

    /**
     * @return \XFDev\LuckyAwards\Repository\UserLuckyAward
     */
    private function getUserLuckyAwardRepo()
    {
        return $this->repository('XFDev\LuckyAwards:UserLuckyAward');
    }
}