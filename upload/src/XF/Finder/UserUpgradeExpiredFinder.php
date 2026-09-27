<?php

namespace XF\Finder;

use XF\Entity\UserUpgradeExpired;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserUpgradeExpired> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserUpgradeExpired> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserUpgradeExpired|null fetchOne(?int $offset = null)
 * @extends Finder<UserUpgradeExpired>
 */
class UserUpgradeExpiredFinder extends Finder
{
}
