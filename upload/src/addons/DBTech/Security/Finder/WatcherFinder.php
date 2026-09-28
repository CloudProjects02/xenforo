<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Security\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Security\Entity\Watcher> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Security\Entity\Watcher> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Security\Entity\Watcher|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Security\Entity\Watcher>
 */
class WatcherFinder extends Finder
{
	/**
	 * @return $this
	 */
	public function orderForList(): WatcherFinder
	{
		$this->order([['watcher_type', 'ASC'], ['priority', 'DESC']], 'DESC');

		return $this;
	}
}