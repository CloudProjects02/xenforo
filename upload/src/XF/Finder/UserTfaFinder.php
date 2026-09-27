<?php

namespace XF\Finder;

use XF\Entity\UserTfa;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserTfa> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserTfa> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserTfa|null fetchOne(?int $offset = null)
 * @extends Finder<UserTfa>
 */
class UserTfaFinder extends Finder
{
}
