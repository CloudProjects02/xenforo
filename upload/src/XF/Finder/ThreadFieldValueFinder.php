<?php

namespace XF\Finder;

use XF\Entity\ThreadFieldValue;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ThreadFieldValue> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ThreadFieldValue> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ThreadFieldValue|null fetchOne(?int $offset = null)
 * @extends Finder<ThreadFieldValue>
 */
class ThreadFieldValueFinder extends Finder
{
}
