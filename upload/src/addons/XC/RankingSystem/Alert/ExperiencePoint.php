<?php

namespace XC\RankingSystem\Alert;

use XF\Alert\AbstractHandler;
use XF\Mvc\Entity\Entity;
class ExperiencePoint extends AbstractHandler
{
    public function getOptOutActions()
    {
        return [
            'mention','remove','level','back_level','manual','double_point'
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