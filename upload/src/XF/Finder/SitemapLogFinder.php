<?php

namespace XF\Finder;

use XF\Entity\SitemapLog;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<SitemapLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<SitemapLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method SitemapLog|null fetchOne(?int $offset = null)
 * @extends Finder<SitemapLog>
 */
class SitemapLogFinder extends Finder
{
}
