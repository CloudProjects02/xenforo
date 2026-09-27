<?php

namespace XF\Finder;

use XF\Entity\TemplateHistory;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<TemplateHistory> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<TemplateHistory> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method TemplateHistory|null fetchOne(?int $offset = null)
 * @extends Finder<TemplateHistory>
 */
class TemplateHistoryFinder extends Finder
{
}
