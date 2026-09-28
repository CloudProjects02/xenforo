<?php

namespace XC\RankingSystem\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Member extends XFCP_Member {
    
    
    public function actionView(ParameterBag $params) {
        
        $parent=parent::actionView($params);
        
        if($parent instanceof \XF\Mvc\Reply\View){
            $user = $this->assertViewableUser($params->user_id);
            
            $lastLevel="";
            $userpoint =  $this->finder('XC\RankingSystem:Levels')->where('exp_points','>',$user->total_points)->order('exp_points','ASC')->fetchOne();
            
              if(!$userpoint){
                  
                $lastLevel=$this->finder('XC\RankingSystem:Levels')->where('exp_points','<',$user->total_points)->order('exp_points','DESC')->fetchOne();
               
              }
            
            $expoints = $this->Finder('XC\RankingSystem:ExperiencePoint')->order('display_order', 'ASC')->fetch();
           
            $badges = $this->Finder('XC\RankingSystem:Badge')->order('display_order', 'ASC')->fetch();
            
            $userbadges = $this->Finder('XC\RankingSystem:AwardBadge')->where('user_id',$user->user_id)->pluckFrom('badge_id')->fetch()->toArray();
            
       		$totalBadges = $this->finder('XC\RankingSystem:Badge')->total();

            // Get dynamic leaderboard achievements based on existing Member Stats
            $leaderboardAchievements = $this->getDynamicLeaderboardAchievements($user->user_id);

            $parent->setParam('userbadges',$userbadges);
            $parent->setParam('badges',$badges);
            $parent->setParam('lastLevel',$lastLevel);
            $parent->setParam('userpoint',$userpoint);
            $parent->setParam('expoints',$expoints);
          	$parent->setParam('totalBadges', $totalBadges);
            $parent->setParam('leaderboardAchievements', $leaderboardAchievements);
        }
        
        return $parent;
    }

    /**
     * Get dynamic leaderboard achievements based on existing Member Stats
     */
    protected function getDynamicLeaderboardAchievements($userId)
    {
        $achievements = [];
        
        // Get all active member stats
        $memberStatRepo = $this->repository('XF:MemberStat');
        $memberStats = $memberStatRepo->findMemberStatsForDisplay()->fetch();
        
        foreach ($memberStats as $memberStat) {
            if (!$memberStat->canView()) {
                continue;
            }
            
            $achievement = $this->getAchievementForMemberStat($memberStat, $userId);
            if ($achievement) {
                $achievements[] = $achievement;
            }
        }
        
        return $achievements;
    }

    /**
     * Get achievement data for a specific Member Stat
     */
    protected function getAchievementForMemberStat($memberStat, $userId)
    {
        $results = $memberStat->getResults();
        
        if (empty($results)) {
            return null;
        }
        
        // Check if user is in the top results
        $userRank = $this->getUserRankInMemberStat($results, $userId);
        if ($userRank === false || $userRank > 5) { // Only show if user is in top 5
            return null;
        }
        
        $userValue = $results[$userId] ?? 0;
        $daysWon = $this->getDaysWonForMemberStat($memberStat->member_stat_key, $userId);
        
        if ($daysWon <= 0) {
            return null;
        }
        
        $achievement = [
            'type' => $memberStat->member_stat_key,
            'title' => $daysWon . ' Days Won!',
            'description' => $this->getAchievementDescription($memberStat),
            'value' => $daysWon,
            'icon' => $this->getAchievementIcon($memberStat->member_stat_key),
            'last_won' => $this->getLastWonDateForMemberStat($memberStat->member_stat_key, $userId),
            'current_rank' => $userRank,
            'current_value' => $userValue,
            'member_stat_title' => $memberStat->title
        ];
        
        return $achievement;
    }

    /**
     * Get user's rank in a member stat
     */
    protected function getUserRankInMemberStat($results, $userId)
    {
        if (!isset($results[$userId])) {
            return false;
        }
        
        $userValue = $results[$userId];
        $rank = 1;
        
        foreach ($results as $id => $value) {
            if ($id == $userId) {
                continue;
            }
            if ($value > $userValue) {
                $rank++;
            }
        }
        
        return $rank;
    }

    /**
     * Get achievement description based on member stat
     */
    protected function getAchievementDescription($memberStat)
    {
        $descriptions = [
            'most_messages' => 'Had the most messages',
            'highest_reaction_score' => 'Had the highest reaction score',
            'most_points' => 'Had the most trophy points',
            'most_solutions' => 'Had the most solutions',
            'staff_members' => 'Was a staff member',
            'todays_birthdays' => 'Had a birthday today'
        ];
        
        return $descriptions[$memberStat->member_stat_key] ?? 'Achieved top ranking';
    }

    /**
     * Get achievement icon based on member stat
     */
    protected function getAchievementIcon($memberStatKey)
    {
        $icons = [
            'most_messages' => '📝',
            'highest_reaction_score' => '⭐',
            'most_points' => '🏆',
            'most_solutions' => '✅',
            'staff_members' => '👑',
            'todays_birthdays' => '🎂'
        ];
        
        return $icons[$memberStatKey] ?? '🏆';
    }

    /**
     * Get days won for a specific member stat
     */
    protected function getDaysWonForMemberStat($memberStatKey, $userId)
    {
        // This is a simplified implementation - you would need to track daily wins
        // For now, we'll use a placeholder that could be enhanced with actual tracking
        
        switch ($memberStatKey) {
            case 'most_messages':
                return $this->getDaysWonForMessages($userId);
            case 'highest_reaction_score':
                return $this->getDaysWonForReactions($userId);
            case 'most_points':
                return $this->getDaysWonForPoints($userId);
            default:
                return 0;
        }
    }

    /**
     * Get last won date for a specific member stat
     */
    protected function getLastWonDateForMemberStat($memberStatKey, $userId)
    {
        // This would need to be implemented with actual tracking
        // For now, returning a placeholder
        return 0;
    }

    /**
     * Get days won for messages (placeholder implementation)
     */
    protected function getDaysWonForMessages($userId)
    {
        try {
            $db = \XF::db();
            
            // Simplified example - count days where user had the most posts
            $sql = "
                SELECT COUNT(DISTINCT DATE(FROM_UNIXTIME(post_date))) as days_won
                FROM xf_post 
                WHERE user_id = ? 
                AND post_date >= ?
                GROUP BY user_id
            ";
            
            $result = $db->fetchOne($sql, [$userId, strtotime('-30 days')]);
            return min($result ?: 0, 10); // Cap at 10 for demo purposes
        } catch (\Exception $e) {
            // If table doesn't exist or query fails, return 0
            return 0;
        }
    }

    /**
     * Get days won for reactions (placeholder implementation)
     */
    protected function getDaysWonForReactions($userId)
    {
        try {
            $db = \XF::db();
            
            // Check if reaction table exists first
            $tableExists = $db->fetchOne("SHOW TABLES LIKE 'xf_reaction_content'");
            if (!$tableExists) {
                return 0;
            }
            
            // Use the correct reaction table name
            $sql = "
                SELECT COUNT(DISTINCT DATE(FROM_UNIXTIME(reaction_date))) as days_won
                FROM xf_reaction_content 
                WHERE content_user_id = ? 
                AND reaction_date >= ?
                GROUP BY content_user_id
            ";
            
            $result = $db->fetchOne($sql, [$userId, strtotime('-30 days')]);
            return min($result ?: 0, 5); // Cap at 5 for demo purposes
        } catch (\Exception $e) {
            // If table doesn't exist or query fails, return 0
            return 0;
        }
    }

    /**
     * Get days won for points (placeholder implementation)
     */
    protected function getDaysWonForPoints($userId)
    {
        // This would depend on your trophy points system
        // For now, returning a placeholder
        return 0;
    }
    
}