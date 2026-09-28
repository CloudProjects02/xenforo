<?php

namespace Siropu\EasyUserBan\Widget;

class ExpiringBans extends \XF\Widget\AbstractWidget
{
	protected $defaultOptions = [
		'limit' => 10
	];

	public function render()
	{
		$userBans = $this->app->repository('XF:Banning')
			->findUserBansForList()
			->where('end_date', '<>', 0)
			->order('end_date', 'DESC')
			->limit($this->options['limit'])
			->fetch();

		$params = [
			'userBans' => $userBans,
			'title'    => $this->getTitle() ?: \XF::phrase('siropu_easy_user_ban_expiring_bans')

		];

		return $this->renderer('siropu_easy_user_ban_expiring_bans_widget', $params);
	}
}
 		  	   				  		 	 	  	  	  		 	  	   			   	  		 			 		 				  		 				   		 				 		  	 	 	   		  			   	 		 	  		 		  	 	 	  		  	 
