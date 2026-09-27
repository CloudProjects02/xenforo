<?php

namespace XF\Finder;

use XF\Entity\ImageProxyReferrer;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ImageProxyReferrer> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ImageProxyReferrer> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ImageProxyReferrer|null fetchOne(?int $offset = null)
 * @extends Finder<ImageProxyReferrer>
 */
class ImageProxyReferrerFinder extends Finder
{
}
