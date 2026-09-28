<?php

/*
 * Created on 20.02.2022
 * HomePage: https://xentr.net
 * Copyright (c) 2019 XENTR | XenForo Add-ons - Styles -  All Rights Reserved
 */

namespace XENTR\ReputationSystem;

class MacroRender
{
	/** @noinspection PhpUnusedParameterInspection */
    /** @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection */
    public static function preRender(\XF\Template\Templater $templater, &$type, &$template, &$name, array &$arguments, array &$globalVars)
    {
        /** @var \XF\Entity\OptionGroup $group */
        $group = $arguments['group'] ?? null;
        if ($group && $group->group_id === 'xentrReputation')
        {
            $template = 'xentr_reputation_macros';
        }
    }
}
 		    		   	 	 		  			  	  	  	 			   			 	 	 	 		 			 		 		 	 		  	  	  	  		 	  		 	  		 	 	  	 	 					 	  	 	 	 	 	 	 	 		 	 			
