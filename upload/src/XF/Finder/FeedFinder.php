<?php

namespace XF\Finder;

use XF\Entity\Feed;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Feed> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Feed> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Feed|null fetchOne(?int $offset = null)
 * @extends Finder<Feed>
 */
class FeedFinder extends Finder
{
	public function isDue($time = null)
	{
		$expression = $this->expression('last_fetch + frequency');
		$this->where($expression, '<', $time ?: time());

		return $this;
	}
}
