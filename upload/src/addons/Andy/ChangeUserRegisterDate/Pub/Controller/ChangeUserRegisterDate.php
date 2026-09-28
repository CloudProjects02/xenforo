<?php

namespace Andy\ChangeUserRegisterDate\Pub\Controller;

use XF\Pub\Controller\AbstractController;

class ChangeUserRegisterDate extends AbstractController
{
	public function actionIndex()
	{
		// check permission
		if (!\XF::visitor()->is_admin)
		{
			return $this->noPermission();
		}

		// send to template
		return $this->view('Andy\ChangeUserRegisterDate:Index', 'andy_change_user_register_date');
	}
	
	public function actionSave()
	{		
		// check permission
		if (!\XF::visitor()->is_admin)
		{
			return $this->noPermission();
		}
        
		// assert post only
		$this->assertPostOnly();
		
        // get date
        $username = $this->filter('username', 'str');
		
		// get user
		$finder = \XF::finder('XF:User');
		$user = $finder
			->where('username', $username)
			->fetchOne();
		
		// check condition
		if (empty($user))
		{
			return $this->error(\XF::phrase('changeuserregisteredate_php_username_not_found'));
		}
		
        // get date
        $date = $this->filter('date', 'str');
		
		// check condition
		if (empty($date))
		{
			return $this->error(\XF::phrase('changeuserregisteredate_php_date_not_set'));
		}
		
        // convert to array
        $date = date_parse($date);
        
        // get time
        $time = $this->filter('time', 'str');
		
		// check condition
		if (empty($time))
		{
			return $this->error(\XF::phrase('changeuserregisteredate_php_time_not_set'));
		}
		
        // convert to array
        $time = date_parse($time);
		
        // get newRegisterDate
        $newRegisterDate = (new \DateTime())->setTimezone(new \DateTimeZone(\XF::visitor()->timezone))
            ->setDate($date['year'], $date['month'], $date['day'])
            ->setTime($time['hour'], $time['minute'])
            ->getTimestamp();

        // check condition
        if ($newRegisterDate > \XF::$time) 
        {
            return $this->error(\XF::phrase('changedate_date_entered_cannot_be_in_the_future'));
        }
		
        // save user
        $user->register_date = $newRegisterDate;
        $user->saveIfChanged();
		
		// send to template
		return $this->view('Andy\ChangeUserRegisterDate:Save', 'andy_change_uesr_register_date_confirm');
	}
}		