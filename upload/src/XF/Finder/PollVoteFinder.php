<?php

namespace XF\Finder;

use XF\Entity\PollVote;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<PollVote> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<PollVote> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method PollVote|null fetchOne(?int $offset = null)
 * @extends Finder<PollVote>
 */
class PollVoteFinder extends Finder
{
}
