<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Security\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Security\Entity\Snapshot> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Security\Entity\Snapshot> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Security\Entity\Snapshot|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Security\Entity\Snapshot>
 */
class SnapshotFinder extends Finder
{
}