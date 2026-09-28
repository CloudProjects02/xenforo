<?php

namespace SV\SignupAbuseBlocking\Option;

use SV\StandardLib\Helper;
use XF\Entity\Option as OptionEntity;
use XF\Option\AbstractOption;

abstract class DefaultLinkAction extends AbstractOption
{
    public static function renderOption(OptionEntity $option, array $htmlParams): string
    {
        $choices = [];

        $app = \XF::app();

        foreach ($app->getContentTypeField('entity') AS $contentType => $entityName)
        {
            $structure = Helper::getEntityStructure($entityName);
            if ($structure === null)
            {
                continue;
            }

            $value = $option->option_value[$contentType] ?? '';
            $choices[] = [
                'phraseName'  => \XF::phrase($app->getContentTypePhraseName($contentType)),
                'contentType' => $contentType,
                'value'       => $value,
                'selected'    => \strlen($value) !== 0,
            ];
        }

        return self::getTemplate(
            'admin:option_template_signup_blocking_default_link_block',
            $option,
            $htmlParams, [
                'choices' => $choices,
                'nextCounter' => \count($choices)
            ]
        );
    }

    public static function verifyOption(array &$values, OptionEntity $option): bool
    {
        if ($option->isInsert())
        {
            // insert - just trust the default value
            return true;
        }

        $contentTypes = \XF::app()->getContentTypeField('entity');
        $output = [];

        foreach ($values AS $contentType => $value)
        {
            if (isset($contentTypes[$contentType]) && ($value['checked'] ?? false))
            {
                $value = $value['value'] ?? '';
                if (\strlen($value) !== 0)
                {
                    $output[$contentType] = $value;
                }
            }
        }

        $values = $output;
        return true;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
