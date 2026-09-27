<?php

namespace XF\Finder;

use XF\Entity\ForumPrompt;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ForumPrompt> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ForumPrompt> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ForumPrompt|null fetchOne(?int $offset = null)
 * @extends Finder<ForumPrompt>
 */
class ForumPromptFinder extends Finder
{
}
