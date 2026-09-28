<?php

namespace SV\SignupAbuseBlocking\NF\Tickets\Entity;

use SV\SignupAbuseBlocking\Entity\MultiAccountUser as MultiAccountUserEntity;
use XF\Mvc\Entity\Structure;

/**
 * @extends \NF\Tickets\Entity\Ticket
 * @property-read ?MultiAccountUserEntity $MultiAccountUser
 */
class Ticket extends XFCP_Ticket
{
    /**
     * @param Structure $structure
     * @return Structure
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->relations['MultiAccountUser'] = [
            'entity'     => 'SV\SignupAbuseBlocking:MultiAccountUser',
            'type'       => self::TO_ONE,
            'conditions' => 'user_id',
            'primary'    => true
        ];
    
        return $structure;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
