<?php

namespace Siropu\EasyUserBan\XF\Entity;

class UserBan extends XFCP_UserBan
{
     public function getBanType()
     {
          return 'global';
     }
     public function getLinkParams()
     {
          return [];
     }
     protected function _postSave()
	{
		parent::_postSave();

          if ($this->end_date)
          {
               $this->setTempUserGroup(true);
          }
          else if ($this->isChanged('end_date') && $this->end_date == 0)
          {
               $this->setTempUserGroup(false);
          }
	}
	protected function _postDelete()
	{
		parent::_postDelete();

          if ($this->end_date)
          {
               $this->setTempUserGroup(false);
          }
	}
     protected function setTempUserGroup($set)
     {
          $userGroupChangeService = $this->app()->service('XF:User\UserGroupChange');

          if ($set)
          {
               $options = \XF::options();

               if ($tempBanGroup = $options->siropuEasyUserBanTempBanGroup)
               {
                    $userGroupChangeService->addUserGroupChange($this->User->user_id, 'tempBanGroup', $tempBanGroup);
               }
          }
          else
          {
               $userGroupChangeService->removeUserGroupChange($this->User->user_id, 'tempBanGroup');
          }
     }
}
 		  	   				  		 	 	  	  	  		 	  	   			   	  		 			 		 				  		 				   		 				 		  	 	 	   		  			   	 		 	  		 		  	 	 	  		  	 
