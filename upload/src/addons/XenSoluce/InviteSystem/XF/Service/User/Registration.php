<?php

namespace XenSoluce\InviteSystem\XF\Service\User;

class Registration extends XFCP_Registration
{

	public function setFromInput(array $input)
	{
	    parent::setFromInput($input);
	    $option = \XF::options();
	    if(($input['code'] ?? '') && $option->xs_is_code_required['mandatory'] == 'no' && $option->xs_is_code_required['user_group_id'] != 0)
        {
            $user = $this->user;
            $user->user_group_id = $option->xs_is_code_required['user_group_id'];
        }
	}
}
