<?php

namespace XF\Finder;

use XF\Entity\ThreadRedirect;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ThreadRedirect> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ThreadRedirect> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ThreadRedirect|null fetchOne(?int $offset = null)
 * @extends Finder<ThreadRedirect>
 */
class ThreadRedirectFinder extends Finder
{
}
