<?php

namespace XF\Finder;

use XF\Entity\PaymentProviderLog;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<PaymentProviderLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<PaymentProviderLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method PaymentProviderLog|null fetchOne(?int $offset = null)
 * @extends Finder<PaymentProviderLog>
 */
class PaymentProviderLogFinder extends Finder
{
}
