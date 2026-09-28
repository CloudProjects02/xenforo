<?php

namespace Jace\TokenDownloads\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Package extends Repository
{
    /**
     * @return Finder
     */
    public function findPackagesForList()
    {
        return $this->finder('Jace\TokenDownloads:Package')
            ->setDefaultOrder('display_order');
    }

    /**
     * @return Finder
     */
    public function findActivePackagesForList()
    {
        return $this->findPackagesForList()
            ->where('active', 1);
    }
} 