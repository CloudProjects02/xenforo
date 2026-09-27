<?php

namespace XF\Finder;

use XF\Entity\UserAlert;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserAlert> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserAlert> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserAlert|null fetchOne(?int $offset = null)
 * @extends Finder<UserAlert>
 */
class UserAlertFinder extends Finder
{
}
