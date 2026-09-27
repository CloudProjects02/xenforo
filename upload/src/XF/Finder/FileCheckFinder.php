<?php

namespace XF\Finder;

use XF\Entity\FileCheck;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<FileCheck> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<FileCheck> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method FileCheck|null fetchOne(?int $offset = null)
 * @extends Finder<FileCheck>
 */
class FileCheckFinder extends Finder
{
}
