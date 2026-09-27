<?php

namespace XF\Finder;

use XF\Entity\UserReject;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserReject> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserReject> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserReject|null fetchOne(?int $offset = null)
 * @extends Finder<UserReject>
 */
class UserRejectFinder extends Finder
{
}
