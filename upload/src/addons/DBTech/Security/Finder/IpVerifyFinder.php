<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Security\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Security\Entity\IpVerify> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Security\Entity\IpVerify> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Security\Entity\IpVerify|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Security\Entity\IpVerify>
 */
class IpVerifyFinder extends Finder
{
}