<?php

namespace XF\Finder;

use XF\Entity\ThreadUserPost;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ThreadUserPost> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ThreadUserPost> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ThreadUserPost|null fetchOne(?int $offset = null)
 * @extends Finder<ThreadUserPost>
 */
class ThreadUserPostFinder extends Finder
{
}
