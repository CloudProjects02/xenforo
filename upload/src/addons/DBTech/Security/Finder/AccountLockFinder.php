<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Security\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Security\Entity\AccountLock> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Security\Entity\AccountLock> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Security\Entity\AccountLock|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Security\Entity\AccountLock>
 */
class AccountLockFinder extends Finder
{
}