<?php

namespace XF\Finder;

use XF\Entity\ConversationRecipient;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ConversationRecipient> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ConversationRecipient> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ConversationRecipient|null fetchOne(?int $offset = null)
 * @extends Finder<ConversationRecipient>
 */
class ConversationRecipientFinder extends Finder
{
}
