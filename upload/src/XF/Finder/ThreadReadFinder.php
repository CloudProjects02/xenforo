<?php

namespace XF\Finder;

use XF\Entity\ThreadRead;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ThreadRead> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ThreadRead> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ThreadRead|null fetchOne(?int $offset = null)
 * @extends Finder<ThreadRead>
 */
class ThreadReadFinder extends Finder
{
}
