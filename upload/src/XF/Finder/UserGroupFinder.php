<?php

namespace XF\Finder;

use XF\Entity\UserGroup;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserGroup> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserGroup> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserGroup|null fetchOne(?int $offset = null)
 * @extends Finder<UserGroup>
 */
class UserGroupFinder extends Finder
{
}
