<?php

namespace XF\Finder;

use XF\Entity\IpMatch;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<IpMatch> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<IpMatch> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method IpMatch|null fetchOne(?int $offset = null)
 * @extends Finder<IpMatch>
 */
class IpMatchFinder extends Finder
{
}
