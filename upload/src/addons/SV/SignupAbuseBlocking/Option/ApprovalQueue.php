<?php

namespace SV\SignupAbuseBlocking\Option;

use SV\StandardLib\Helper;
use XF\Entity\Option as OptionEntity;
use XF\Option\AbstractOption;

abstract class ApprovalQueue extends AbstractOption
{
    public static function renderOption(OptionEntity $option, array $htmlParams): string
    {
        $contentTypes = \array_keys(\XF::app()->getContentTypeField('approval_queue_handler_class'));

        $value = [];
        $choices = [];
        $app = \XF::app();
        foreach ($contentTypes AS $type)
        {
            $entityName = $app->getContentTypeEntity($type, false);
            $structure = Helper::getEntityStructure($entityName);
            if ($structure === null)
            {
                continue;
            }

            if ($option->option_value[$type] ?? false)
            {
                $value[] = $type;
            }
            $choices[$type] = \XF::app()->getContentTypePhrase($type);
        }

        return self::getCheckboxRow($option, $htmlParams, $choices, $value);
    }

    public static function verifyOption(array &$choices, OptionEntity $option): bool
    {
        if ($option->isInsert())
        {
            // insert - just trust the default value
            return true;
        }

        $contentTypes = \XF::app()->getContentTypeField('approval_queue_handler_class');
        $values = [];
        foreach ($choices AS $contentType)
        {
            if ($contentTypes[$contentType] ?? false)
            {
                $values[$contentType] = true;
            }
        }

        $choices = $values;

        return true;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
