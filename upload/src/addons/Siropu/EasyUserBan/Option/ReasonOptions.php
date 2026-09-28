<?php

namespace Siropu\EasyUserBan\Option;

class ReasonOptions extends \XF\Option\AbstractOption
{
	public static function verifyOption(array &$value)
	{
		$value = array_filter($value, function($val)
          {
               return !empty($val);
          });

		return true;
	}
}
 		  	   				  		 	 	  	  	  		 	  	   			   	  		 			 		 				  		 				   		 				 		  	 	 	   		  			   	 		 	  		 		  	 	 	  		  	 
