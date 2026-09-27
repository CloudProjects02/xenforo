<?php

namespace XF\Finder;

use XF\Entity\TemplateModificationLog;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<TemplateModificationLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<TemplateModificationLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method TemplateModificationLog|null fetchOne(?int $offset = null)
 * @extends Finder<TemplateModificationLog>
 */
class TemplateModificationLogFinder extends Finder
{
}
