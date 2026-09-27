<?php

namespace XF\Finder;

use XF\Entity\ThreadPromptGroup;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ThreadPromptGroup> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ThreadPromptGroup> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ThreadPromptGroup|null fetchOne(?int $offset = null)
 * @extends Finder<ThreadPromptGroup>
 */
class ThreadPromptGroupFinder extends Finder
{
}
