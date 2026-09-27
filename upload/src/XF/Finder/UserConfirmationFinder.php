<?php

namespace XF\Finder;

use XF\Entity\UserConfirmation;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserConfirmation> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserConfirmation> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserConfirmation|null fetchOne(?int $offset = null)
 * @extends Finder<UserConfirmation>
 */
class UserConfirmationFinder extends Finder
{
}
