<?php

namespace XF\Finder;

use XF\Entity\LoginAttempt;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<LoginAttempt> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<LoginAttempt> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method LoginAttempt|null fetchOne(?int $offset = null)
 * @extends Finder<LoginAttempt>
 */
class LoginAttemptFinder extends Finder
{
}
