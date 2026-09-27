<?php

namespace XF\Finder;

use XF\Entity\ConversationMaster;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ConversationMaster> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ConversationMaster> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ConversationMaster|null fetchOne(?int $offset = null)
 * @extends Finder<ConversationMaster>
 */
class ConversationMasterFinder extends Finder
{
}
