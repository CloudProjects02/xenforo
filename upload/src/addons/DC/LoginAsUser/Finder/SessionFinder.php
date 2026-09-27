<?php

declare(strict_types=1);

namespace DC\LoginAsUser\Finder;

use DC\LoginAsUser\Entity\Session;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Session> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Session> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Session|null fetchOne(?int $offset = null)
 * @extends Finder<Session>
 */
class SessionFinder extends Finder
{
	public function onlyActive(): self
	{
		$this->where('end_date', 0);

		return $this;
	}

	public function forActor(int $userId): self
	{
		$this->where('actor_user_id', $userId);

		return $this;
	}

	public function forTarget(int $userId): self
	{
		$this->where('target_user_id', $userId);

		return $this;
	}

	/**
	 * Half-open on both ends so the caller can pass either bound alone. The end bound covers the
	 * whole day the ACP date picker names - a filter that says "to 3 March" and drops everything
	 * that happened on 3 March is a bug report waiting to happen.
	 */
	public function startedBetween(?int $start, ?int $end): self
	{
		if ($start)
		{
			$this->where('start_date', '>=', $start);
		}

		if ($end)
		{
			$this->where('start_date', '<', $end + 86400);
		}

		return $this;
	}
}
