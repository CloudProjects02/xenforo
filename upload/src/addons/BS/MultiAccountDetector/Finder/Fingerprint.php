<?php

namespace BS\MultiAccountDetector\Finder;

use XF\Mvc\Entity\Finder;

class Fingerprint extends Finder
{
	public function fingerNotUser(\XF\Entity\User $user, $fingerprint)
	{
		$this->where([
			['fingerprint', $fingerprint],
			['user_id', '!=', $user->user_id]
		]);

		return $this;
	}

	public function forUser(\XF\Entity\User $user)
	{
		$this->where([['user_id', '=', $user->user_id]]);

		return $this;
	}
}