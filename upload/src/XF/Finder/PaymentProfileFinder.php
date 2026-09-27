<?php

namespace XF\Finder;

use XF\Entity\PaymentProfile;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<PaymentProfile> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<PaymentProfile> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method PaymentProfile|null fetchOne(?int $offset = null)
 * @extends Finder<PaymentProfile>
 */
class PaymentProfileFinder extends Finder
{
}
