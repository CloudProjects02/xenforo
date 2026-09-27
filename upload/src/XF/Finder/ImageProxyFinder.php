<?php

namespace XF\Finder;

use XF\Entity\ImageProxy;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ImageProxy> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ImageProxy> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ImageProxy|null fetchOne(?int $offset = null)
 * @extends Finder<ImageProxy>
 */
class ImageProxyFinder extends Finder
{
}
