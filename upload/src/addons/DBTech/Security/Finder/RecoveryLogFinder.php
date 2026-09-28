<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Security\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Security\Entity\RecoveryLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Security\Entity\RecoveryLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Security\Entity\RecoveryLog|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Security\Entity\RecoveryLog>
 */
class RecoveryLogFinder extends Finder
{
}