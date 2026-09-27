<?php

namespace XF\Finder;

use XF\Entity\PollResponse;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<PollResponse> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<PollResponse> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method PollResponse|null fetchOne(?int $offset = null)
 * @extends Finder<PollResponse>
 */
class PollResponseFinder extends Finder
{
}
