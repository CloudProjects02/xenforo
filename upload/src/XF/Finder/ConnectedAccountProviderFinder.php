<?php

namespace XF\Finder;

use XF\Entity\ConnectedAccountProvider;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ConnectedAccountProvider> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ConnectedAccountProvider> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ConnectedAccountProvider|null fetchOne(?int $offset = null)
 * @extends Finder<ConnectedAccountProvider>
 */
class ConnectedAccountProviderFinder extends Finder
{
}
