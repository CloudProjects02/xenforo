<?php

namespace XF\Finder;

use XF\Entity\UserOption;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserOption> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserOption> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserOption|null fetchOne(?int $offset = null)
 * @extends Finder<UserOption>
 */
class UserOptionFinder extends Finder
{
}
