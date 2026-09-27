<?php

namespace XF\Finder;

use XF\Entity\Draft;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Draft> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Draft> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Draft|null fetchOne(?int $offset = null)
 * @extends Finder<Draft>
 */
class DraftFinder extends Finder
{
}
