<?php

namespace XF\Finder;

use XF\Entity\UserUpgrade;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserUpgrade> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserUpgrade> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserUpgrade|null fetchOne(?int $offset = null)
 * @extends Finder<UserUpgrade>
 */
class UserUpgradeFinder extends Finder
{
}
