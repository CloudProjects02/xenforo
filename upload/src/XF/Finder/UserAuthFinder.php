<?php

namespace XF\Finder;

use XF\Entity\UserAuth;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserAuth> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserAuth> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserAuth|null fetchOne(?int $offset = null)
 * @extends Finder<UserAuth>
 */
class UserAuthFinder extends Finder
{
}
