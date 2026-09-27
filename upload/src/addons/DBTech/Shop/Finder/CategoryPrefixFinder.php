<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Shop\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Shop\Entity\CategoryPrefix> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Shop\Entity\CategoryPrefix> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Shop\Entity\CategoryPrefix|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Shop\Entity\CategoryPrefix>
 */
class CategoryPrefixFinder extends Finder
{
}