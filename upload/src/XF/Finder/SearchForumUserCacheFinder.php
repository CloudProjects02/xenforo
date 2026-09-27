<?php

namespace XF\Finder;

use XF\Entity\SearchForumUserCache;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<SearchForumUserCache> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<SearchForumUserCache> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method SearchForumUserCache|null fetchOne(?int $offset = null)
 * @extends Finder<SearchForumUserCache>
 */
class SearchForumUserCacheFinder extends Finder
{
}
