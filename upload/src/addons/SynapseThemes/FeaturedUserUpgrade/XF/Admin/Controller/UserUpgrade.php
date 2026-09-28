<?php

namespace SynapseThemes\FeaturedUserUpgrade\XF\Admin\Controller;

class UserUpgrade extends XFCP_UserUpgrade
{
    protected function upgradeSaveProcess(\XF\Entity\UserUpgrade $userUpgrade)
    {
        $form = parent::upgradeSaveProcess($userUpgrade);
        
        $input = $this->filter([
            'is_featured' => 'bool'
        ]);
        
        $form->setup(function() use ($userUpgrade, $input)
        {
            $userUpgrade->is_featured = $input['is_featured'];
        });
        
        return $form;
    }
} 