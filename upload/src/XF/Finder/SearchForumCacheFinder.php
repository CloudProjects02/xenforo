<?php

namespace XF\Finder;

use XF\Entity\SearchForumCache;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<SearchForumCache> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<SearchForumCache> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method SearchForumCache|null fetchOne(?int $offset = null)
 * @extends Finder<SearchForumCache>
 */
class SearchForumCacheFinder extends Finder
{
}
