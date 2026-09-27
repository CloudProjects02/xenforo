<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Shop\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Shop\Entity\ItemFieldValue> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Shop\Entity\ItemFieldValue> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Shop\Entity\ItemFieldValue|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Shop\Entity\ItemFieldValue>
 */
class ItemFieldValueFinder extends Finder
{
}