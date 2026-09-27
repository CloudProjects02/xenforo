<?php

namespace XF\Finder;

use XF\Entity\UserPushOptOut;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserPushOptOut> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserPushOptOut> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserPushOptOut|null fetchOne(?int $offset = null)
 * @extends Finder<UserPushOptOut>
 */
class UserPushOptOutFinder extends Finder
{
}
