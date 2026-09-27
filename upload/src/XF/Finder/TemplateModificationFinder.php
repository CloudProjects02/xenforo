<?php

namespace XF\Finder;

use XF\Entity\TemplateModification;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<TemplateModification> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<TemplateModification> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method TemplateModification|null fetchOne(?int $offset = null)
 * @extends Finder<TemplateModification>
 */
class TemplateModificationFinder extends Finder
{
}
