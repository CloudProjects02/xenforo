<?php

namespace XC\RankingSystem\Alert;

use XF\Alert\AbstractHandler;
use XF\Mvc\Entity\Entity;
class Badge extends AbstractHandler
{
    public function getOptOutActions()
    {
        return [
            'award'
        ];
    }

    public function getOptOutDisplayOrder()
    {
        return 250;
    }
    
   public function canViewContent(Entity $entity, &$error = null)
	{
		return true;
	}
}