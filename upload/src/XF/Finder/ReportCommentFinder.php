<?php

namespace XF\Finder;

use XF\Entity\ReportComment;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ReportComment> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ReportComment> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ReportComment|null fetchOne(?int $offset = null)
 * @extends Finder<ReportComment>
 */
class ReportCommentFinder extends Finder
{
}
