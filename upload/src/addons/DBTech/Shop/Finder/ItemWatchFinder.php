<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Shop\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Shop\Entity\ItemWatch> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Shop\Entity\ItemWatch> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Shop\Entity\ItemWatch|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Shop\Entity\ItemWatch>
 */
class ItemWatchFinder extends Finder
{
}