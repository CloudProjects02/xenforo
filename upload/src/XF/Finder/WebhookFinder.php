<?php

namespace XF\Finder;

use XF\Entity\Webhook;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Webhook> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Webhook> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Webhook|null fetchOne(?int $offset = null)
 * @extends Finder<Webhook>
 */
class WebhookFinder extends Finder
{
}
