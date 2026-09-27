<?php

namespace XF\Finder;

use XF\Entity\UserAlertOptOut;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserAlertOptOut> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserAlertOptOut> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserAlertOptOut|null fetchOne(?int $offset = null)
 * @extends Finder<UserAlertOptOut>
 */
class UserAlertOptOutFinder extends Finder
{
}
