<?php

namespace XF\Finder;

use XF\Entity\TagResultCache;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<TagResultCache> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<TagResultCache> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method TagResultCache|null fetchOne(?int $offset = null)
 * @extends Finder<TagResultCache>
 */
class TagResultCacheFinder extends Finder
{
}
