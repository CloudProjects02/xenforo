<?php

namespace XF\Finder;

use XF\Entity\ThreadQuestion;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ThreadQuestion> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ThreadQuestion> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ThreadQuestion|null fetchOne(?int $offset = null)
 * @extends Finder<ThreadQuestion>
 */
class ThreadQuestionFinder extends Finder
{
}
