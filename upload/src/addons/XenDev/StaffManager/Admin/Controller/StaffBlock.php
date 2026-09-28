<?php

namespace XenDev\StaffManager\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XenDev\StaffManager\Entity\StaffBlock as StaffBlockEntity;

class StaffBlock extends AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertAdminPermission('user');
    }

    public function actionIndex()
    {
        $blocks = $this->finder('XenDev\StaffManager:StaffBlock')
            ->with(['UserGroup', 'Node'])
            ->order('display_order')
            ->fetch();

        return $this->view(
            'XenDev\StaffManager:StaffBlock\Listing',
            'xendev_staffmanager_block_list',
            ['blocks' => $blocks]
        );
    }

    protected function blockAddEdit(StaffBlockEntity $block)
    {
        $userGroups = $this->repository('XF:UserGroup')->findUserGroupsForList()->fetch();

        $nodes = $this->finder('XF:Node')
            ->with('NodeType')
            ->order('lft')
            ->fetch();

        return $this->view(
            'XenDev\StaffManager:StaffBlock\Edit',
            'xendev_staffmanager_block_edit',
            [
                'block' => $block,
                'userGroups' => $userGroups,
                'nodes' => $nodes
            ]
        );
    }

    public function actionAdd()
    {
        $block = $this->em()->create('XenDev\StaffManager:StaffBlock');
        return $this->blockAddEdit($block);
    }

    public function actionEdit(ParameterBag $params)
    {
        $block = $this->assertBlockExists($params->block_id);
        return $this->blockAddEdit($block);
    }

    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params->block_id)
        {
            $block = $this->assertBlockExists($params->block_id);
        }
        else
        {
            $block = $this->em()->create('XenDev\StaffManager:StaffBlock');
        }

        $input = $this->filter([
            'source_type' => 'str',
            'user_group_id' => 'uint',
            'node_id' => 'uint',
            'group_match_type' => 'str',
            'display_order' => 'uint',
            'icon_class' => 'str',
            'active' => 'bool'
        ]);

        $allowedSourceTypes = ['user_group', 'admin', 'global_mod', 'node_mod'];
        if (!in_array($input['source_type'], $allowedSourceTypes, true))
        {
            $input['source_type'] = 'user_group';
        }

        $allowedMatchTypes = ['primary', 'secondary', 'both'];
        if (!in_array($input['group_match_type'], $allowedMatchTypes, true))
        {
            $input['group_match_type'] = 'both';
        }

        $title = '';

        if ($input['source_type'] === 'user_group')
        {
            if (!$input['user_group_id'])
            {
                return $this->error('Lütfen bir kullanıcı grubu seçin.');
            }

            $userGroup = $this->em()->find('XF:UserGroup', $input['user_group_id']);
            if (!$userGroup)
            {
                return $this->error('Geçerli bir kullanıcı grubu seçin.');
            }

            $title = $userGroup->title;
            $input['node_id'] = 0;
        }
        elseif ($input['source_type'] === 'admin')
        {
            $title = 'Sistem Yöneticileri';
            $input['user_group_id'] = 0;
            $input['node_id'] = 0;
            $input['group_match_type'] = 'both';
        }
        elseif ($input['source_type'] === 'global_mod')
        {
            $title = 'Genel Moderatörler';
            $input['user_group_id'] = 0;
            $input['node_id'] = 0;
            $input['group_match_type'] = 'both';
        }
        elseif ($input['source_type'] === 'node_mod')
        {
            if (!$input['node_id'])
            {
                return $this->error('Lütfen bir kategori seçin.');
            }

            $node = $this->em()->find('XF:Node', $input['node_id']);
            if (!$node)
            {
                return $this->error('Geçerli bir kategori seçin.');
            }

            $title = $node->title . ' Moderatörleri';
            $input['user_group_id'] = 0;
            $input['group_match_type'] = 'both';
        }

        $input['title'] = $title;

        $form = $this->formAction();
        $form->basicEntitySave($block, $input);
        $form->run();

        return $this->redirect($this->buildLink('staff-blocks'));
    }

    public function actionDelete(ParameterBag $params)
    {
        $block = $this->assertBlockExists($params->block_id);

        if ($this->isPost())
        {
            $block->delete();
            return $this->redirect($this->buildLink('staff-blocks'));
        }

        return $this->view(
            'XenDev\StaffManager:StaffBlock\Delete',
            'xendev_staffmanager_block_delete',
            ['block' => $block]
        );
    }

    protected function assertBlockExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('XenDev\StaffManager:StaffBlock', $id, $with, $phraseKey);
    }
}
 		  					 	  		   	  			   	  			  	    	 				     		 			 		 		 	  			     	  	 		 			  	   	   	 		  	    		  			      	      		  
