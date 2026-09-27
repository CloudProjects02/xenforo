<?php

namespace XF\Finder;

use XF\Entity\ThreadPrefixGroup;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ThreadPrefixGroup> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ThreadPrefixGroup> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ThreadPrefixGroup|null fetchOne(?int $offset = null)
 * @extends Finder<ThreadPrefixGroup>
 */
class ThreadPrefixGroupFinder extends Finder
{
}
