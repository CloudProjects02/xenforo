<?php

namespace XF\Finder;

use XF\Entity\UnfurlResult;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UnfurlResult> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UnfurlResult> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UnfurlResult|null fetchOne(?int $offset = null)
 * @extends Finder<UnfurlResult>
 */
class UnfurlResultFinder extends Finder
{
}
