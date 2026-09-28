<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Security\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Security\Entity\Session> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Security\Entity\Session> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Security\Entity\Session|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Security\Entity\Session>
 */
class SessionFinder extends Finder
{
}