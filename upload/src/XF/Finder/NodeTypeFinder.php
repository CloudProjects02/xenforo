<?php

namespace XF\Finder;

use XF\Entity\NodeType;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<NodeType> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<NodeType> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method NodeType|null fetchOne(?int $offset = null)
 * @extends Finder<NodeType>
 */
class NodeTypeFinder extends Finder
{
}
