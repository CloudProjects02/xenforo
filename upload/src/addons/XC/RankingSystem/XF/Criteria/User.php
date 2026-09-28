<?php

namespace XC\RankingSystem\XF\Criteria;

use XF\Util\Arr;
use function in_array,
             is_array;

class User extends XFCP_User {

    public function _matchReachXp($data, $user) {


        if (isset($data['num'])) {

            return $user->total_points > (int) $data['num'] || (int) $data['num'] == $user->total_points;
        }
    }

    public function _matchThreadCategories($data, $user) {



        if (isset($data['categories']) && count($data['categories']) && isset($data['num'])) {

            $forumsBadges = array_filter($data['categories']);

            $badgeRepo = \XF::repository('XC\RankingSystem:Badge');

            $threadCount = $badgeRepo->maxThreadInCategories($forumsBadges, $user->user_id);

            return $threadCount > (int) $data['num'] || (int) $data['num'] == $threadCount;
        }
    }

    public function _matchPositiveFeedback($data, $user) {



        $badgeRepo = \XF::repository('XC\RankingSystem:Badge');

        if (isset($data['num'])) {

            $positiveFeedbackCount = $badgeRepo->positivefeedback($user->user_id);

            return $positiveFeedbackCount > (int) $data['num'] || (int) $data['num'] == $positiveFeedbackCount;
        }
    }

    public function _matchPositiveReputation($data, $user) {


        $badgeRepo = \XF::repository('XC\RankingSystem:Badge');

        if (isset($data['num'])) {

            $positiveReputationCount = $badgeRepo->positiveReputation($user->user_id);

            return $positiveReputationCount > (int) $data['num'] || (int) $data['num'] == $positiveReputationCount;
        }
    }

    public function _matchThreadRepliesCategories($data, $user) {



        if (isset($data['categories']) && count($data['categories']) && isset($data['num'])) {

            $forumsBadges = array_filter($data['categories']);

            $badgeRepo = \XF::repository('XC\RankingSystem:Badge');

            $threadCount = $badgeRepo->maxRepliesInCategories($forumsBadges, $user->user_id);

            return $threadCount > (int) $data['num'] || (int) $data['num'] == $threadCount;
        }
    }

    public function _matchReachLevel($data, $user) {


        if (isset($data['num'])) {

            $badgeRepo = \XF::repository('XC\RankingSystem:Badge');

            list($levelpositiion, $level) = $badgeRepo->maxReachLevel($user);

            if ($levelpositiion == "nextlevel") {

                return $level > (int) $data['num'];
            }

            if ($levelpositiion == "lastlevel") {

                return $level == (int) $data['num'];
            }
        }
    }

    public function _matchSolveBestAnswer($data, $user) {

        if (isset($data['num'])) {


            $badgeRepo = \XF::repository('XC\RankingSystem:Badge');

            $bestAnswersCount = $badgeRepo->maxSolveBestAnswer($user->user_id);

            return $bestAnswersCount > (int) $data['num'] || (int) $data['num'] == $bestAnswersCount;
        }
    }

    public function _matchReferMember($data, $user) {



        if (isset($data['num'])) {


            $badgeRepo = \XF::repository('XC\RankingSystem:Badge');

            $ReferMemberCount = $badgeRepo->maxReferMember($user->user_id);

            return $ReferMemberCount > (int) $data['num'] || (int) $data['num'] == $ReferMemberCount;
        }
    }

    public function _matchReachPosts($data, $user) {


        $badgeRepo = \XF::repository('XC\RankingSystem:Badge');

        $ReachPosts = $badgeRepo->maxReachPosts($user->user_id);

        return $ReachPosts > (int) $data['num'] || (int) $data['num'] == $ReachPosts;
    }

    public function _matchReachTotalPostLike($data, $user) {

        if (isset($data['num'])) {

            $badgeRepo = \XF::repository('XC\RankingSystem:Badge');

            $ReachPostLikes = $badgeRepo->ReachTotalPostLike($data, $user);

            return $ReachPostLikes > (int) $data['num'] || (int) $data['num'] == $ReachPostLikes;
        }
    }

    public function _matchXThreadXReplies($data, $user) {



        if (isset($data['categories']) && count($data['categories']) && isset($data['thread']) && isset($data['replies'])) {

            $forumsBadges = array_filter($data['categories']);

            $badgeRepo = \XF::repository('XC\RankingSystem:Badge');

            $ReachPosts = $badgeRepo->XThreadXReplies($data['thread'], $data['replies'], $forumsBadges, $user);

            return $ReachPosts;
        }
    }

    public function _matchLoginDailyStreak($data, $user) {


        $badgeRepo = \XF::repository('XC\RankingSystem:Badge');

        $streakDays = $badgeRepo->LoginDailyStreak($user);

        return $streakDays > (int) $data['num'] || (int) $data['num'] == $streakDays;
    }
}
