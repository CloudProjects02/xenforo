<?php

namespace XF\Finder;

use XF\Entity\UserChangeTemp;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserChangeTemp> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserChangeTemp> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserChangeTemp|null fetchOne(?int $offset = null)
 * @extends Finder<UserChangeTemp>
 */
class UserChangeTempFinder extends Finder
{
}
