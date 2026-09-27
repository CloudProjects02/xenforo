<?php

namespace XF\Finder;

use XF\Entity\SearchForum;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<SearchForum> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<SearchForum> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method SearchForum|null fetchOne(?int $offset = null)
 * @extends Finder<SearchForum>
 */
class SearchForumFinder extends Finder
{
}
