<?php

namespace XF\Finder;

use XF\Entity\ThreadPrompt;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ThreadPrompt> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ThreadPrompt> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ThreadPrompt|null fetchOne(?int $offset = null)
 * @extends Finder<ThreadPrompt>
 */
class ThreadPromptFinder extends Finder
{
}
