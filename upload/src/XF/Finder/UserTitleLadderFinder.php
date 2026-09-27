<?php

namespace XF\Finder;

use XF\Entity\UserTitleLadder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserTitleLadder> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserTitleLadder> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserTitleLadder|null fetchOne(?int $offset = null)
 * @extends Finder<UserTitleLadder>
 */
class UserTitleLadderFinder extends Finder
{
}
