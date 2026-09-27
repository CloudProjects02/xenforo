<?php

namespace XF\Finder;

use XF\Entity\ForumField;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ForumField> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ForumField> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ForumField|null fetchOne(?int $offset = null)
 * @extends Finder<ForumField>
 */
class ForumFieldFinder extends Finder
{
}
