<?php
/**
 * @noinspection PhpUnusedParameterInspection
 */

namespace SV\SignupAbuseBlocking\Admin\Controller;

use SV\SignupAbuseBlocking\ControllerPlugin\MultipleAccount as MultipleAccountPlugin;
use SV\SignupAbuseBlocking\Repository\MultipleAccount;
use SV\SignupAbuseBlocking\XF\Entity\User as ExtendedUserEntity;
use SV\StandardLib\Helper;
use XF\Admin\Controller\AbstractController;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View as ViewReply;
use XF\Util\Arr;

class MultiAccount extends AbstractController
{
    protected function preDispatchController($action, ParameterBag $params): void
    {
        parent::preDispatchController($action, $params);
        $this->assertAdminPermission('user');
    }

    public function actionIndex(ParameterBag $params): AbstractReply
    {
        $linkParams = [];
        $user = null;
        $userId = (int)$this->filter('user_id', 'int');
        if ($userId !== 0)
        {
            /** @var ExtendedUserEntity|null $user */
            $user = Helper::find(UserEntity::class, $userId, ['Profile', 'MultiAccountUser']);
            if ($user !== null)
            {
                $total = 1;
                $linkParams['user_id'] = $userId;
                $multiAccountHits = new ArrayCollection([$userId => $user->MultiAccountUser]);
            }
            else
            {
                $total = 0;
                $multiAccountHits = new ArrayCollection([]);
            }

            $page = 1;
            $perPage = 1;
        }
        else
        {
            $page = $this->filterPage();
            $perPage = 30;
            $finder = MultipleAccount::get()->getFinderForList();

            $linkParams['order'] = $this->filter('order', 'str');
            switch ($linkParams['order'])
            {
                case 'register':
                    $finder->order('User.register_date', 'desc');
                    break;
                default:
                case 'last_seen':
                    unset($linkParams['order']);
                    break;
            }
            $finder->order('last_seen_date', 'desc')
                   ->order('first_seen_date', 'desc');

            $linkParams['users'] = $this->filter('users', 'str');
            $linkParams['emails'] = $this->filter('emails', 'str');
            $linkParams['exact_match'] = $this->filter('exact_match', 'bool');
            if ($linkParams['exact_match'])
            {
                if ($linkParams['users'] !== '')
                {
                    $finder->where('User.username', $linkParams['users']);
                }

                if ($linkParams['emails'] !== '')
                {
                    $finder->where('User.email', $linkParams['emails']);
                }
            }
            else
            {
                $usersArr = Arr::stringToArray($linkParams['users'], '/\s*,\s*/');
                if (\count($usersArr))
                {
                    foreach ($usersArr as &$username)
                    {
                        $username = '%' . $username . '%';
                    }
                    $finder->where('User.username', 'like', $usersArr);
                }

                $emailsArr = Arr::stringToArray($linkParams['emails'], '/\s*,\s*/');
                if (\count($emailsArr))
                {
                    foreach ($emailsArr as &$email)
                    {
                        $email = '%' . $email . '%';
                    }
                    $finder->where('User.email', 'like', $emailsArr);
                }
            }

            $total = $finder->total();
            $this->assertValidPage($page, $perPage, $total, 'anti-spam/multi-accounts', $linkParams);

            if ($this->isPost())
            {
                if ($page > 1)
                {
                    $linkParams['page'] = $page;
                }

                return $this->redirect($this->buildLink('anti-spam/multi-accounts', null, $linkParams));
            }
            $multiAccountHits = $finder->limitByPage($page, $perPage)->fetch();
        }
        $viewParams = [
            'user' => $user,
            'multiAccountHits' => $multiAccountHits,

            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'linkParams' => $linkParams,
        ];

        return $this->view('SV\SignupAbuseBlocking:MultiAccount\List', 'svSignupAbuseBlocking_multi_account_list', $viewParams);
    }

    public function actionEvents(ParameterBag $params): AbstractReply
    {
        $this->setSectionContext('svMultiAccountEvents');

        $user = null;
        $userId = (int)$this->filter('user_id', 'int');
        if ($userId !== 0)
        {
            $user = Helper::find(UserEntity::class, $userId);
            if ($user === null)
            {
                return $this->notFound();
            }
        }

        $multipleAccountPlugin = Helper::plugin($this, MultipleAccountPlugin::class);

        $response = $multipleAccountPlugin->actionMultipleAccountEventList(
            $user,
            'anti-spam/multi-accounts/events',
            'SV\SignupAbuseBlocking\XF:User\MultipleAccounts',
            [],
            true
        );
        if ($response instanceof ViewReply)
        {
            $response->setTemplateName('sv_multiple_account_list');
        }
        return $response;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
