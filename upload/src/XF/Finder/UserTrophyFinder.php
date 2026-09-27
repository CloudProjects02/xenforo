<?php

namespace XF\Finder;

use XF\Entity\UserTrophy;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserTrophy> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserTrophy> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserTrophy|null fetchOne(?int $offset = null)
 * @extends Finder<UserTrophy>
 */
class UserTrophyFinder extends Finder
{
}
