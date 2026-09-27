<?php

namespace XF\Finder;

use XF\Entity\SpamTriggerLog;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<SpamTriggerLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<SpamTriggerLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method SpamTriggerLog|null fetchOne(?int $offset = null)
 * @extends Finder<SpamTriggerLog>
 */
class SpamTriggerLogFinder extends Finder
{
	public function forContent($contentType, $contentId)
	{
		$this->where('content_type', $contentType)
			->where('content_id', $contentId);

		return $this;
	}
}
