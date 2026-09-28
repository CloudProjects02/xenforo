<?php

namespace XC\RankingSystem\Service;

class LevelBadge extends \XF\Service\AbstractService
{
    public function getUserLevelBadge($user)
    {
        $levelService = $this->service('XC\RankingSystem:Level');
        $levelData = $levelService->caluPercentage($user);

        if (!$levelData || !isset($levelData['next_level'])) {
            return null;
        }

        $currentLevel = $levelData['next_level'];
        
        // Find the level badge for the current level
        $levelBadge = $this->finder('XC\RankingSystem:LevelBadge')
            ->where('level', $currentLevel)
            ->where('is_active', true)
            ->fetchOne();

        return $levelBadge;
    }

    public function getUserLevelProgress($user)
    {
        $levelService = $this->service('XC\RankingSystem:Level');
        $levelData = $levelService->caluPercentage($user);

        if (!$levelData) {
            return [
                'current_level' => 0,
                'next_level' => 0,
                'percentage' => 0,
                'points_needed' => 0,
                'level_badge' => null
            ];
        }

        // The next_level from levelData is actually the current level the user is at
        $currentLevel = $levelData['next_level'];
        
        // Calculate the next level the user is working towards
        $nextLevel = $this->calculateNextLevel($user, $currentLevel);
        
        // Get the badge for the current level
        $levelBadge = $this->getLevelBadgeByLevel($currentLevel);

        // Calculate points needed for next level (only if there is a next level)
        $pointsNeeded = 0;
        if ($nextLevel > $currentLevel) {
            $pointsNeeded = $this->calculatePointsNeeded($user, $nextLevel);
        }

        return [
            'current_level' => $currentLevel,
            'next_level' => $nextLevel,
            'percentage' => $levelData['perc'],
            'points_needed' => $pointsNeeded,
            'level_badge' => $levelBadge
        ];
    }

    /**
     * Calculate points needed for the next level
     */
    protected function calculatePointsNeeded($user, $nextLevel)
    {
        // Find the next level's required points
        $nextLevelData = $this->finder('XC\RankingSystem:Levels')
            ->where('level', $nextLevel)
            ->fetchOne();

        if (!$nextLevelData) {
            return 0;
        }

        $currentPoints = $user->total_points ?? 0;
        $nextLevelPoints = $nextLevelData->exp_points;
        
        // Calculate how many more points are needed
        $pointsNeeded = $nextLevelPoints - $currentPoints;
        
        return max(0, $pointsNeeded); // Ensure it's not negative
    }

    /**
     * Calculate the next level the user is working towards
     */
    protected function calculateNextLevel($user, $currentLevel)
    {
        if (!$user->total_points) {
            return 0;
        }

        // Find the next level (higher than current level)
        $nextLevel = $this->finder('XC\RankingSystem:Levels')
            ->where('level', '>', $currentLevel)
            ->order('level', 'ASC')
            ->fetchOne();

        return $nextLevel ? $nextLevel->level : $currentLevel; // Return current level if no next level exists
    }

    public function getLevelBadgeByLevel($level)
    {
        return $this->finder('XC\RankingSystem:LevelBadge')
            ->where('level', $level)
            ->where('is_active', true)
            ->fetchOne();
    }
}
