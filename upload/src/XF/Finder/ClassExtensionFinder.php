<?php

namespace XF\Finder;

use XF\Entity\ClassExtension;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ClassExtension> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ClassExtension> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ClassExtension|null fetchOne(?int $offset = null)
 * @extends Finder<ClassExtension>
 */
class ClassExtensionFinder extends Finder
{
}
