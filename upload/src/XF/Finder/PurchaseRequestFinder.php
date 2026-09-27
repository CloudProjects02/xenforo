<?php

namespace XF\Finder;

use XF\Entity\PurchaseRequest;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<PurchaseRequest> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<PurchaseRequest> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method PurchaseRequest|null fetchOne(?int $offset = null)
 * @extends Finder<PurchaseRequest>
 */
class PurchaseRequestFinder extends Finder
{
}
