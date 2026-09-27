<?php

namespace XF\Finder;

use XF\Entity\ForumPrefix;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ForumPrefix> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ForumPrefix> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ForumPrefix|null fetchOne(?int $offset = null)
 * @extends Finder<ForumPrefix>
 */
class ForumPrefixFinder extends Finder
{
}
