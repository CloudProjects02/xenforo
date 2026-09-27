<?php

namespace XF\Finder;

use XF\Entity\MemberStat;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<MemberStat> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<MemberStat> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method MemberStat|null fetchOne(?int $offset = null)
 * @extends Finder<MemberStat>
 */
class MemberStatFinder extends Finder
{
	public function activeOnly()
	{
		$this
			->where('active', 1)
			->whereAddOnActive();

		return $this;
	}

	public function cacheableOnly()
	{
		$this->where('cache_lifetime', '>', 0);

		return $this;
	}
}
