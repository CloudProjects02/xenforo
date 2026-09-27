<?php

namespace XF\Finder;

use XF\Entity\UserRemember;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserRemember> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserRemember> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserRemember|null fetchOne(?int $offset = null)
 * @extends Finder<UserRemember>
 */
class UserRememberFinder extends Finder
{
}
