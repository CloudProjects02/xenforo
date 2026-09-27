<?php

namespace XF\Finder;

use XF\Entity\UserBan;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserBan> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserBan> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserBan|null fetchOne(?int $offset = null)
 * @extends Finder<UserBan>
 */
class UserBanFinder extends Finder
{
}
