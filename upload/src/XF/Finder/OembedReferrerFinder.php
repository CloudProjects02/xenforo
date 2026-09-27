<?php

namespace XF\Finder;

use XF\Entity\OembedReferrer;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<OembedReferrer> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<OembedReferrer> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method OembedReferrer|null fetchOne(?int $offset = null)
 * @extends Finder<OembedReferrer>
 */
class OembedReferrerFinder extends Finder
{
}
