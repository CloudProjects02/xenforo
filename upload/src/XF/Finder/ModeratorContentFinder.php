<?php

namespace XF\Finder;

use XF\Entity\ModeratorContent;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ModeratorContent> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ModeratorContent> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ModeratorContent|null fetchOne(?int $offset = null)
 * @extends Finder<ModeratorContent>
 */
class ModeratorContentFinder extends Finder
{
}
