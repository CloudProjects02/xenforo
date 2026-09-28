<?php

namespace XC\RankingSystem\Service;

class ExperienctPoint extends \XF\Service\AbstractService
{
    public function findExperiencePointForList()
    {
        return $this->finder("XC\RankingSystem:ExperiencePoint")->order(
            "display_order"
        );
    }

    public function manuallyAwardXPToUser(
        \XC\RankingSystem\Entity\ExperiencePoint $expPoint,
        \XF\Entity\User $user
    ) {
        $inserted = $this->db()->insert(
            "xc_user_xp",
            [
                "user_id" => $user->user_id,
                "xp_id" => $expPoint->xp_id,
                "award_date" => \XF::$time,
                "manual" => 1,
                "spot_ex_point" => \xf::options()->xc_double_xp
                    ? $expPoint->points * 2
                    : $expPoint->points,
            ],
            false,
            false,
            "IGNORE"
        );

        if ($inserted) {
            if (\xf::options()->xc_double_xp) {
                $user->fastUpdate(
                    "total_points",
                    $user->total_points + $expPoint->points * 2
                );
            } else {
                $user->fastUpdate(
                    "total_points",
                    $user->total_points + $expPoint->points
                );
            }

            if (\xf::options()->xc_alert_onoff) {
                $alertRepo = $this->repository("XF:UserAlert");
                $alertRepo->alertFromUser(
                    $user,
                    $user,
                    "experience_point",
                    $expPoint->xp_id,
                    "manual",
                    [
                        "message" => \xf::phrase("xc_manuallly_award_point"),
                        "point" => $expPoint->points,
                    ]
                );
            }

            return true;
        } else {
            return false;
        }
    }

    public function checkXP($pointType)
    {
        return $this->finder("XC\RankingSystem:ExperiencePoint")
            ->where("point_type", $pointType)
            ->fetchOne();
    }

    public function checkXPWithId($pointType)
    {
        return $this->finder("XC\RankingSystem:ExperiencePoint")
            ->where("point_type", $pointType)
            ->fetchOne();
    }

    public function AwardXPToUser(
        \XC\RankingSystem\Entity\ExperiencePoint $expPoint,
        \XF\Entity\User $user,
        $contentId,
        $AlertOn = null
    ) {
        $inserted = $this->db()->insert(
            "xc_user_xp",
            [
                "user_id" => $user->user_id,
                "xp_id" => $expPoint->xp_id,
                "award_date" => \XF::$time,
                "manual" => 0,
                "content_type" => $expPoint->point_type,
                "content_id" => $contentId,
                "spot_ex_point" => \xf::options()->xc_double_xp
                    ? $expPoint->points * 2
                    : $expPoint->points,
            ],
            false,
            false,
            "IGNORE"
        );

        if ($inserted) {
            if (\xf::options()->xc_double_xp) {
                $user->fastUpdate(
                    "total_points",
                    $user->total_points + $expPoint->points * 2
                );
            } else {
                $user->fastUpdate(
                    "total_points",
                    $user->total_points + $expPoint->points
                );
            }

            $this->UpdateUserLevel($user, $expPoint);

            if (
                \xf::options()->xc_double_xp &&
                $AlertOn &&
                \xf::options()->xc_alert_onoff
            ) {
                $alertRepo = $this->repository("XF:UserAlert");
                $alertRepo->alertFromUser(
                    $user,
                    $user,
                    "experience_point",
                    $expPoint->xp_id,
                    "double_point"
                );
            } elseif ($AlertOn && \xf::options()->xc_alert_onoff) {
                /** @var \XF\Repository\UserAlert $alertRepo */
                $alertRepo = $this->repository("XF:UserAlert");
                $alertRepo->alertFromUser(
                    $user,
                    $user,
                    "experience_point",
                    $expPoint->xp_id,
                    "mention"
                );
            }

            return true;
        } else {
            return false;
        }
    }

    public function checkXpAwardUser($content_type, $content_id)
    {
        return $this->finder("XC\RankingSystem:AwardXp")
            ->where("content_type", $content_type)
            ->where("content_id", $content_id)
            ->fetchOne();
    }

    public function UpdateUserLevel($user, $expPoint)
    {
        $pointlevel = $this->finder("XC\RankingSystem:Levels")
            ->where("exp_points", ">", $user->total_points)
            ->order("exp_points", "ASC")
            ->fetchOne();

        if (!$pointlevel) {
            $pointlevel = $this->finder("XC\RankingSystem:Levels")
                ->where("exp_points", "=", $user->total_points)
                ->order("exp_points", "ASC")
                ->fetchOne();
        }

        if (!$pointlevel) {
            $lastLevel = $this->finder("XC\RankingSystem:Levels")
                ->where("exp_points", "<", $user->total_points)
                ->order("exp_points", "DESC")
                ->fetchOne();

                if($lastLevel && $lastLevel->level == $this->getMaxLevel()) {
                    $this->handlePrestige($user);
                    return true;
                }    

            if (!$lastLevel) {
                $lastLevel = $this->finder("XC\RankingSystem:Levels")
                    ->where("exp_points", "=", $user->total_points)
                    ->order("exp_points", "DESC")
                    ->fetchOne();
            }

            if ($lastLevel) {
                if ($lastLevel->level > $user->level) {
                    $alertRepo = $this->repository("XF:UserAlert");
                    $alertRepo->alertFromUser(
                        $user,
                        $user,
                        "experience_point",
                        $expPoint->xp_id,
                        "level",
                        [
                            "message" => \xf::phrase("xc_reach_next_level"),
                            "level" => $lastLevel->level,
                        ]
                    );
                }

                $user->fastUpdate("level", $lastLevel->level);
            }
        }

        if ($pointlevel) {
            if ($pointlevel->level < $user->level) {
                $alertRepo = $this->repository("XF:UserAlert");
                $alertRepo->alertFromUser(
                    $user,
                    $user,
                    "experience_point",
                    $expPoint->xp_id,
                    "back_level",
                    [
                        "message" => \xf::phrase("xc_reach_next_level"),
                        "level" => $pointlevel->level,
                    ]
                );
            }

            $user->fastUpdate("level", $pointlevel->level);

            return true;
        }
    }

    protected function handlePrestige($user)
    {
        $db = $this->db();
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Increment prestige level
            $user->fastUpdate('prestige_level', $user->prestige_level + 1);
            
            // Reset experience points
            $user->fastUpdate('total_points', 0);
            
            // Reset level to 1
            $user->fastUpdate('level', 1);
            
            // Create prestige alert
            if(\XF::options()->xc_alert_onoff) {
                $alertRepo = $this->repository('XF:UserAlert');
                $alertRepo->alertFromUser(
                    $user,
                    $user,
                    'experience_point',
                    0,
                    'prestige',
                    ['prestige' => $user->prestige_level]
                );
            }
            
            $db->commit();
        } catch(\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    protected function getMaxLevel()
    {
        return $this->finder('XC\RankingSystem:Levels')
            ->order('level', 'DESC')
            ->fetchOne()
            ->level;
    }
}
