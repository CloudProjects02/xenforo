<?php

namespace XF\Finder;

use XF\Entity\ReactionContent;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ReactionContent> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ReactionContent> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ReactionContent|null fetchOne(?int $offset = null)
 * @extends Finder<ReactionContent>
 */
class ReactionContentFinder extends Finder
{
}
