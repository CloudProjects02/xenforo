<?php

namespace SV\SignupAbuseBlocking\XF\Service\User;

use XF\App;

/**
 * @Extends \XF\Service\User\DeleteCleanUp
 */
class DeleteCleanUp extends XFCP_DeleteCleanUp
{
    public function __construct(App $app, $userId, $userName)
    {
        if (\XF::options()->svSignupAbuseBlockingFullDeleteForGDPR ?? false)
        {
            $this->deletes['xf_sv_user_registration_log'] = 'user_id = ?';
            $this->deletes['xf_sv_multiple_account_token'] = 'user_id = ?';
            $this->deletes['xf_sv_multiple_account_log'] = 'user_id = ?';
            $this->deletes['xf_sv_multiple_account_event'] = 'triggering_user_id = ?';
        }

        $this->deletes['xf_sv_multiple_account_report_data_user'] = 'user_id = ?';
        $this->deletes['xf_sv_login_log'] = 'user_id = ?';
        parent::__construct($app, $userId, $userName);
    }

    protected function stepMiscCleanUp()
    {
        if ($this->userId && (\XF::options()->svSignupAbuseBlockingFullDeleteForGDPR ?? false))
        {
            $this->app->jobManager()->enqueueLater('svCleanupMultiAccountState',
                \XF::$time + 5,
                'SV\SignupAbuseBlocking:CleanupMultiAccountState', [
                    'userId' => $this->userId
                ], false);
        }

        parent::stepMiscCleanUp();
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
