<?php

namespace XF\Finder;

use XF\Entity\CodeEvent;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<CodeEvent> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<CodeEvent> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method CodeEvent|null fetchOne(?int $offset = null)
 * @extends Finder<CodeEvent>
 */
class CodeEventFinder extends Finder
{
}
