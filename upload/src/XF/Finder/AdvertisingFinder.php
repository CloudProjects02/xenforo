<?php

namespace XF\Finder;

use XF\Entity\Advertising;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Advertising> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Advertising> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Advertising|null fetchOne(?int $offset = null)
 * @extends Finder<Advertising>
 */
class AdvertisingFinder extends Finder
{
}
