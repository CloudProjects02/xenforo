<?php

namespace XF\Finder;

use XF\Entity\UserTfaTrusted;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserTfaTrusted> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserTfaTrusted> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserTfaTrusted|null fetchOne(?int $offset = null)
 * @extends Finder<UserTfaTrusted>
 */
class UserTfaTrustedFinder extends Finder
{
}
