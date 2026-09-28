<?php

namespace MMO\ResourceDownloaders\XF\Entity;

class User extends XFCP_User
{
    public function canViewWhoDownloadedResource()
    {
        return $this->hasPermission('resource', 'viewWhoDownResAny');
    }
}
 		    	 			 									    		 		   	 	  		 		 	    				 		 	  	 				  	 		 	  	 			 	 		    		 		    		   	 	 		   				 					 					 	
