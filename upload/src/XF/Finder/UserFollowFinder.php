<?php

namespace XF\Finder;

use XF\Entity\UserFollow;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserFollow> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserFollow> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserFollow|null fetchOne(?int $offset = null)
 * @extends Finder<UserFollow>
 */
class UserFollowFinder extends Finder
{
}
