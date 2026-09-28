<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Security\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Security\Entity\CompromisedLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Security\Entity\CompromisedLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Security\Entity\CompromisedLog|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Security\Entity\CompromisedLog>
 */
class CompromisedLogFinder extends Finder
{
}