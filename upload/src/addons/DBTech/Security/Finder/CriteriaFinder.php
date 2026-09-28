<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Security\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Security\Entity\Criteria> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Security\Entity\Criteria> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Security\Entity\Criteria|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Security\Entity\Criteria>
 */
class CriteriaFinder extends Finder
{
}