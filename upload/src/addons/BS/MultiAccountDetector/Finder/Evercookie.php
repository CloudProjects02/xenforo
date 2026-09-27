<?php

namespace BS\MultiAccountDetector\Finder;

use XF\Mvc\Entity\Finder;

class Evercookie extends Finder
{
	public function cookieNotUser(\XF\Entity\User $user, $cookie)
	{
		$this->where([
			['evercookie', $cookie],
			['user_id', '!=', $user->user_id]
		]);

		return $this;
	}

	public function forUser(\XF\Entity\User $user)
	{
		$this->where(['user_id', '=', $user->user_id]);

		return $this;
	}
}