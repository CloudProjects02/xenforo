<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Security\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Security\Entity\ChangeLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Security\Entity\ChangeLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Security\Entity\ChangeLog|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Security\Entity\ChangeLog>
 */
class ChangeLogFinder extends Finder
{
}