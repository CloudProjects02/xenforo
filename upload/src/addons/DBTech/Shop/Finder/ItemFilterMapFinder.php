<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Shop\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Shop\Entity\ItemFilterMap> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Shop\Entity\ItemFilterMap> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Shop\Entity\ItemFilterMap|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Shop\Entity\ItemFilterMap>
 */
class ItemFilterMapFinder extends Finder
{
}