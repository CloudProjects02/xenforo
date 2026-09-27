<?php

namespace XF\Finder;

use XF\Entity\ContentVote;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ContentVote> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ContentVote> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ContentVote|null fetchOne(?int $offset = null)
 * @extends Finder<ContentVote>
 */
class ContentVoteFinder extends Finder
{
}
