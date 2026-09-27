<?php

namespace XF\Finder;

use XF\Entity\Search;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Search> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Search> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Search|null fetchOne(?int $offset = null)
 * @extends Finder<Search>
 */
class SearchFinder extends Finder
{
}
