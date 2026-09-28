<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Security\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Security\Entity\LoginStrike> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Security\Entity\LoginStrike> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Security\Entity\LoginStrike|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Security\Entity\LoginStrike>
 */
class LoginStrikeFinder extends Finder
{
}