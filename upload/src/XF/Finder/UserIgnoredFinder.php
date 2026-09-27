<?php

namespace XF\Finder;

use XF\Entity\UserIgnored;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserIgnored> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserIgnored> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserIgnored|null fetchOne(?int $offset = null)
 * @extends Finder<UserIgnored>
 */
class UserIgnoredFinder extends Finder
{
}
