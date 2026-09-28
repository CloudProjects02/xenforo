<?php

namespace EAEAddons\ThreadCount\Repository;

use XF\Mvc\Entity\Repository;

class Thread extends Repository
{
	public function getUserThreadCount($userId)
	{
		return $this->app()->finder('XF:Thread')
			->where('user_id', $userId)
			->where('discussion_state', 'visible')
			->with('Forum')
			->where('Forum.count_messages', 1)
			->total();
	}
}