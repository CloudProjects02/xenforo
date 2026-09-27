<?php

namespace XF\Finder;

use XF\Entity\CaptchaLog;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<CaptchaLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<CaptchaLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method CaptchaLog|null fetchOne(?int $offset = null)
 * @extends Finder<CaptchaLog>
 */
class CaptchaLogFinder extends Finder
{
}
