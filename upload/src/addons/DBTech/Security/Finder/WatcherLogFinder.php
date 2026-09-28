<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Security\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Security\Entity\WatcherLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Security\Entity\WatcherLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Security\Entity\WatcherLog|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Security\Entity\WatcherLog>
 */
class WatcherLogFinder extends Finder
{
}