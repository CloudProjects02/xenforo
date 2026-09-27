<?php

namespace XF\Finder;

use XF\Entity\FeedLog;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<FeedLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<FeedLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method FeedLog|null fetchOne(?int $offset = null)
 * @extends Finder<FeedLog>
 */
class FeedLogFinder extends Finder
{
}
