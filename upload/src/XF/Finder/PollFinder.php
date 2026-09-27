<?php

namespace XF\Finder;

use XF\Entity\Poll;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Poll> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Poll> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Poll|null fetchOne(?int $offset = null)
 * @extends Finder<Poll>
 */
class PollFinder extends Finder
{
}
