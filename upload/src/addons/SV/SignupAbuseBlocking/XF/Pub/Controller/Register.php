<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SignupAbuseBlocking\XF\Pub\Controller;

use SV\SignupAbuseBlocking\Entity\Token as TokenEntity;
use SV\SignupAbuseBlocking\Globals;
use SV\SignupAbuseBlocking\Repository\MultipleAccount as MultipleAccountRepo;
use SV\SignupAbuseBlocking\XF\Entity\User as ExtendedUserEntity;
use XF\ConnectedAccount\ProviderData\AbstractProviderData;
use XF\Entity\User as UserEntity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception as ReplyException;
use XF\Mvc\Reply\Redirect as RedirectReply;
use XF\Service\User\RegisterForm as UserRegisterFormSvc;
use SV\SignupAbuseBlocking\Repository\UserRegistrationLog as UserRegistrationLogRepo;

class Register extends XFCP_Register
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        Globals::$armLoginPlugin = true;
        parent::preDispatchController($action, $params);
    }

    protected function assertRegistrationActive()
    {
        parent::assertRegistrationActive();
        $this->assertRegistrationNotThrottling();
    }

    protected function assertRegistrationNotThrottling(): void
    {
        $ip = $this->app()->request()->getIp();
        if (UserRegistrationLogRepo::get()->isSignupsThrottled($ip))
        {
            throw $this->exception($this->error(\XF::phrase('new_registrations_currently_not_being_accepted')));
        }
    }

    protected function assertRegRedirect(string $link, $data = [], array $params = [])
    {
        $redirectedRegistration = Globals::$redirectedRegistration ?? false;
        if ($redirectedRegistration)
        {
            return;
        }
        try
        {
            // note; this ensures we do not have infinite loops during update(s)...
            $this->assertCanonicalUrl($this->buildLink($link, $data, $params));
        }
        catch (ReplyException $e)
        {
            // rewrite the reply redirect from permanent to temporary
            $response = $e->getReply();
            if ($response instanceof RedirectReply)
            {
                $multipleAccountRepo = MultipleAccountRepo::get();

                $receivedToken = $multipleAccountRepo->getCookieValue('register');
                if ($receivedToken)
                {
                    // capture any existing tracking cookie and push it to the next step
                    $session = $this->session();
                    $session->set('svRegCookie', $receivedToken);

                    $multipleAccountRepo->setCookieValue(null);
                }

                $response->setType('temporary');
            }

            throw $e;
        }
    }

    public function actionIndex()
    {
        $this->assertRegRedirect('register');

        return parent::actionIndex();
    }

    public function actionRegister()
    {
        $this->assertRegRedirect('register/register');

        return parent::actionRegister();
    }

    public function actionConnectedAccount(ParameterBag $params)
    {
        $this->assertRegRedirect('register/connected-accounts', $params->params());

        return parent::actionConnectedAccount($params);
    }

    public function actionConnectedAccountAssociate(ParameterBag $params)
    {
        $this->assertRegRedirect('register/connected-accounts/associate', $params->params());

        return parent::actionConnectedAccountAssociate($params);
    }

    public function actionConnectedAccountRegister(ParameterBag $params)
    {
        $this->assertRegRedirect('register/connected-accounts/register', $params->params());

        return parent::actionConnectedAccountRegister($params);
    }

    protected function setupRegistration(array $input)
    {
        Globals::$duringRegistration = true;
        return parent::setupRegistration($input);
    }

    protected function setupConnectedRegistration(array $input, AbstractProviderData $providerData)
    {
        Globals::$duringRegistration = true;
        return parent::setupConnectedRegistration($input, $providerData);
    }

    /**
     * @param UserEntity|ExtendedUserEntity $user
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected function finalizeRegistration(UserEntity $user)
    {
        // call parent 1st to setup the session fully
        parent::finalizeRegistration($user);

        Globals::$duringRegistration = false;

        $multipleAccountRepo = MultipleAccountRepo::get();
        $receivedToken = $multipleAccountRepo->getCookieValue('register');
        if (!$receivedToken)
        {
            /** @var TokenEntity $token */
            $token = $user->AccountDetectionToken;
            $multipleAccountRepo->setCookieValue($token);
        }

        $dataCollection = Globals::$dataCollection ?? [];
        MultipleAccountRepo::get()->postRegistrationMultipleAccountDetection($user, $dataCollection);
    }

    /**
     * @param UserRegisterFormSvc $regForm
     *
     * @return array
     */
    protected function getRegistrationInput(UserRegisterFormSvc $regForm)
    {
        $input = parent::getRegistrationInput($regForm);

        if ($this->options()->svSADAB_requestWebsiteOnSignup ?? false)
        {
            $input['website'] = $this->filter('website', 'str');
        }

        return $input;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
