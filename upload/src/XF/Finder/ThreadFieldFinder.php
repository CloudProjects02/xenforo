<?php

namespace XF\Finder;

use XF\Entity\ThreadField;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ThreadField> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ThreadField> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ThreadField|null fetchOne(?int $offset = null)
 * @extends Finder<ThreadField>
 */
class ThreadFieldFinder extends Finder
{
}
