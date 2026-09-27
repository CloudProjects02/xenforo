<?php

namespace Andrew\ModeratorPanel\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class UserNoteCategory extends Repository
{

    public function fetchCategoryList()
    {
        return $this->finder('Andrew\ModeratorPanel:UserNoteCategory')
            ->setDefaultOrder('display_order');
    }
    public function findCategoryByTitle($title)
    {
        return $this->finder('Andrew\ModeratorPanel:UserNoteCategory')
            ->where('title', $title);
    }

}