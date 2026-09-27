<?php

namespace XF\Finder;

use XF\Entity\Ip;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Ip> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Ip> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Ip|null fetchOne(?int $offset = null)
 * @extends Finder<Ip>
 */
class IpFinder extends Finder
{
}
