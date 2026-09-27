<?php

namespace XF\Finder;

use XF\Entity\PaymentProvider;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<PaymentProvider> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<PaymentProvider> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method PaymentProvider|null fetchOne(?int $offset = null)
 * @extends Finder<PaymentProvider>
 */
class PaymentProviderFinder extends Finder
{
}
