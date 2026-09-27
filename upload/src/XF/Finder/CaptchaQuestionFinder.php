<?php

namespace XF\Finder;

use XF\Entity\CaptchaQuestion;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<CaptchaQuestion> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<CaptchaQuestion> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method CaptchaQuestion|null fetchOne(?int $offset = null)
 * @extends Finder<CaptchaQuestion>
 */
class CaptchaQuestionFinder extends Finder
{
}
