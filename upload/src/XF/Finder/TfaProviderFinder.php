<?php

namespace XF\Finder;

use XF\Entity\TfaProvider;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<TfaProvider> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<TfaProvider> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method TfaProvider|null fetchOne(?int $offset = null)
 * @extends Finder<TfaProvider>
 */
class TfaProviderFinder extends Finder
{
}
