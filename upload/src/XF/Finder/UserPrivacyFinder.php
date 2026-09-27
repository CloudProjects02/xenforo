<?php

namespace XF\Finder;

use XF\Entity\UserPrivacy;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserPrivacy> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserPrivacy> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserPrivacy|null fetchOne(?int $offset = null)
 * @extends Finder<UserPrivacy>
 */
class UserPrivacyFinder extends Finder
{
}
