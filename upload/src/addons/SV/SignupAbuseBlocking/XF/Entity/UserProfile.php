<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SignupAbuseBlocking\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property bool $multiple_account_detection_alertable
 */
class UserProfile extends XFCP_UserProfile
{
    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['multiple_account_detection_alertable'] = ['type' => self::BOOL, 'default' => true];

        foreach (['signature', 'website', 'location', 'about'] AS $column)
        {
            if (\array_key_exists($column, $structure->columns))
            {
                $structure->columns[$column]['checkForSpamPhrases'] = true;
            }
        }

        foreach (['website', 'location'] AS $column)
        {
            if (\array_key_exists($column, $structure->columns))
            {
                $structure->columns[$column]['checkForSpamPhrasesProfileEdit'] = true;
            }
        }

        return $structure;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
