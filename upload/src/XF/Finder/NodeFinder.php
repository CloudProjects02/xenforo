<?php

namespace XF\Finder;

use XF\Entity\Node;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Node> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Node> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Node|null fetchOne(?int $offset = null)
 * @extends Finder<Node>
 */
class NodeFinder extends Finder
{
	public function descendantOf(Node $node)
	{
		$this->where('lft', '>', $node->lft)
			->where('rgt', '<', $node->rgt);

		return $this;
	}

	public function listable()
	{
		$this->where('display_in_list', 1);

		return $this;
	}
}
