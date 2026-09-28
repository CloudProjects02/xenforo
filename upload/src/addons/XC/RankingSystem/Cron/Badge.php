<?php

namespace XC\RankingSystem\Cron;


class Badge
{
    
	public static function runBadgeCheck()
	{
		

		$badgeRepo = \XF::repository('XC\RankingSystem:Badge');
		$badges = $badgeRepo->findBadgesForList()->fetch();
		if (!count($badges))
		{
			return;
		}

	
                $finder = \XF::finder('XF:User');
               
                $users = $finder->whereOr(
                    ['latest_visit', '>',time() - 24*60*60],
                    ['last_activity','>',time() - 24*60*60]
                )->fetch();



		foreach ($users AS $user)
		{
			$badgeRepo->updateBadgesForUser($user, $badges);
		}
	}
}