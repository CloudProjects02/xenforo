<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Security\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Security\Entity\BadBehavior> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Security\Entity\BadBehavior> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Security\Entity\BadBehavior|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Security\Entity\BadBehavior>
 */
class BadBehaviorFinder extends Finder
{
}