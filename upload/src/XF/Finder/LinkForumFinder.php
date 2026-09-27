<?php

namespace XF\Finder;

use XF\Entity\LinkForum;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<LinkForum> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<LinkForum> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method LinkForum|null fetchOne(?int $offset = null)
 * @extends Finder<LinkForum>
 */
class LinkForumFinder extends Finder
{
}
