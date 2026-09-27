<?php

namespace XF\Finder;

use XF\Entity\Purchasable;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Purchasable> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Purchasable> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Purchasable|null fetchOne(?int $offset = null)
 * @extends Finder<Purchasable>
 */
class PurchasableFinder extends Finder
{
}
