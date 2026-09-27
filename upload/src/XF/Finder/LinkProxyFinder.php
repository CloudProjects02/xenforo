<?php

namespace XF\Finder;

use XF\Entity\LinkProxy;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<LinkProxy> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<LinkProxy> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method LinkProxy|null fetchOne(?int $offset = null)
 * @extends Finder<LinkProxy>
 */
class LinkProxyFinder extends Finder
{
}
