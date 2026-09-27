<?php

namespace XF\Finder;

use XF\Entity\TfaAttempt;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<TfaAttempt> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<TfaAttempt> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method TfaAttempt|null fetchOne(?int $offset = null)
 * @extends Finder<TfaAttempt>
 */
class TfaAttemptFinder extends Finder
{
}
