<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Security\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Security\Entity\FingerprintLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Security\Entity\FingerprintLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Security\Entity\FingerprintLog|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Security\Entity\FingerprintLog>
 */
class FingerprintLogFinder extends Finder
{
}