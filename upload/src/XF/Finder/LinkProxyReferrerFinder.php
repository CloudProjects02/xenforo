<?php

namespace XF\Finder;

use XF\Entity\LinkProxyReferrer;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<LinkProxyReferrer> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<LinkProxyReferrer> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method LinkProxyReferrer|null fetchOne(?int $offset = null)
 * @extends Finder<LinkProxyReferrer>
 */
class LinkProxyReferrerFinder extends Finder
{
}
