<?php

namespace XF\Finder;

use XF\Entity\BbCode;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<BbCode> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<BbCode> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method BbCode|null fetchOne(?int $offset = null)
 * @extends Finder<BbCode>
 */
class BbCodeFinder extends Finder
{
}
