<?php

namespace XF\Finder;

use XF\Entity\UserProfile;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserProfile> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserProfile> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserProfile|null fetchOne(?int $offset = null)
 * @extends Finder<UserProfile>
 */
class UserProfileFinder extends Finder
{
}
