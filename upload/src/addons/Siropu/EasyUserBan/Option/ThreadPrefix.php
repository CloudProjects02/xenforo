<?php

namespace Siropu\EasyUserBan\Option;

class ThreadPrefix extends \XF\Option\AbstractOption
{
	public static function renderSelect(\XF\Entity\Option $option, array $htmlParams)
	{
		$prefixes = [\XF::phrase('(none)')->render()];

		$prefixes += \XF::repository('XF:ThreadPrefix')
			->findPrefixesForList()
			->fetch()
			->pluckNamed('title', 'prefix_id');

		return self::getSelectRow($option, $htmlParams, $prefixes);
	}
     public static function renderSelectMultiple(\XF\Entity\Option $option, array $htmlParams)
	{
		$prefixes = \XF::repository('XF:ThreadPrefix')
			->findPrefixesForList()
			->fetch()
			->pluckNamed('title', 'prefix_id');

          $choices = [];

		foreach ($prefixes AS $prefixId => $title)
		{
			$choices[$prefixId] = [
				'value' => $prefixId,
				'label' => \XF::escapeString($title)
			];
		}

          $controlOptions = self::getControlOptions($option, $htmlParams);
		$controlOptions['multiple'] = true;
		$controlOptions['size'] = 4;

		return self::getTemplater()->formSelectRow($controlOptions, $choices, self::getRowOptions($option, $htmlParams));
     }
}
 		  	   				  		 	 	  	  	  		 	  	   			   	  		 			 		 				  		 				   		 				 		  	 	 	   		  			   	 		 	  		 		  	 	 	  		  	 
