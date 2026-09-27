<?php

namespace XF\Finder;

use XF\Entity\AdvertisingPosition;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<AdvertisingPosition> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<AdvertisingPosition> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method AdvertisingPosition|null fetchOne(?int $offset = null)
 * @extends Finder<AdvertisingPosition>
 */
class AdvertisingPositionFinder extends Finder
{
}
