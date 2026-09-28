<?php

namespace Andy\ChangeResourceDate\XFRM\Pub\Controller;

use XF\Mvc\ParameterBag;

class ResourceItem extends XFCP_ResourceItem
{
	public function actionChangeResourceDate(ParameterBag $params)
	{
		// get visitor
		$visitor = \XF::visitor();
		
		// get permission
		if (!$visitor->hasPermission('changeResourceDate', 'view'))
		{
			return $this->noPermission();
		}
		
		// get resource
		$resource = $this->assertViewableResource($params->resource_id);
		
		// check condition
		if ($resource->update_count != 0)
		{
			return $this->error(\XF::phrase('changeresourcedate_php_changing_resource_date_is_not_allowed'));
		}
		
		// get timezone
		$timezone = $visitor['timezone'];
		
		// set timezone
		date_default_timezone_set($timezone);
		
		// get currentTime
		$currentTime = date('H:i', time());
        
		// get currentDate
		$currentDate = date('Y-m-d', time());

		// prepare viewParams
		$viewParams = [
			'resource' => $resource,
            'currentTime' => $currentTime,
            'currentDate' => $currentDate
		];
		
		// send to template
		return $this->view('Andy\ChangeResourceDate:ChangeResourceDate', 'andy_change_resource_date', $viewParams);
	}
	
	public function actionChangeResourceDateSave(ParameterBag $params)
	{
		// get visitor
		$visitor = \XF::visitor();
				
		// get permission
		if (!$visitor->hasPermission('changeResourceDate', 'view'))
		{
			return $this->noPermission();
		}
		
		// get resource
		$resource = $this->assertViewableResource($params->resource_id);

		// assert post only
		$this->assertPostOnly();
		
        // get date
        $date = $this->filter('date', 'str');
        
        // get time
        $time = $this->filter('time', 'str');
        
        // convert to array
        $date = date_parse($date);
        
        // convert to array
        $time = date_parse($time);

        // get newDate
        $newDate = (new \DateTime())->setTimezone(new \DateTimeZone(\XF::visitor()->timezone))
            ->setDate($date['year'], $date['month'], $date['day'])
            ->setTime($time['hour'], $time['minute'])
            ->getTimestamp();
		
        // check condition
        if ($newDate > \XF::$time) 
		{
			return $this->error(\XF::phrase('changeresourcedate_php_date_entered_cannot_be_in_the_future'));
		}

		// update resource
		$resource->resource_date = $newDate;
		$resource->last_update = $newDate;
		$resource->save();

		// update resource version
		$finder = \XF::finder('XFRM:ResourceVersion');
		$result = $finder
			->where('resource_id', $params->resource_id)
			->fetchOne();      

		// get data
		$data = [
			'release_date' => $newDate
		];

		// update
		$result->fastUpdate($data);
		
		// update resource update
		$finder = \XF::finder('XFRM:ResourceUpdate');
		$result = $finder
			->where('resource_id', $params->resource_id)
			->order('resource_update_id', 'DESC')
			->fetchOne();      

		// get data
		$data = [
			'post_date' => $newDate
		];

		// update
		$result->fastUpdate($data);

		// return redirect
		return $this->redirect(
			$this->getDynamicRedirect($this->buildLink('resources', $resource), false)
		);
	}
}