<?php
/**
 * @noinspection PhpUnusedParameterInspection
 */

namespace SV\SignupAbuseBlocking\Admin\Controller;

use SV\SignupAbuseBlocking\Repository\AntiSpam as AntiSpamRepo;
use SV\StandardLib\Helper;
use XF\Admin\Controller\AbstractController;
use XF\Entity\Option as OptionEntity;
use XF\Entity\OptionGroup as OptionGroupEntity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Repository\Option as OptionRepo;
use function array_key_exists;

class AntiSpam extends AbstractController
{
    protected function preDispatchController($action, ParameterBag $params): void
    {
        $this->assertAdminPermission('svAntiSpam');
    }

    public function actionOption(ParameterBag $params): AbstractReply
    {
        $option = $this->assertOptionExists($params['option_id']);

        return $this->redirect($this->buildLink('anti-spam/options') . '#' . $option->option_id);
    }

    public function actionOptions(ParameterBag $params): AbstractReply
    {
        $group = $this->assertAntiSpamOptionGroupExists();

        $viewParams = [
            'group' => $group,
            'options' => AntiSpamRepo::get()->getAntiSpamOptions($group),
        ];
        return $this->view('XF:Option\Listing', 'svSignupBlocking_option_list', $viewParams);
    }

    public function actionOptionsUpdate(): AbstractReply
    {
        $this->assertPostOnly();
        $group = $this->assertAntiSpamOptionGroupExists();

        $input = $this->filter([
            'options_listed' => 'array-str',
            'options' => 'array'
        ]);
        $antiSpamOptions = AntiSpamRepo::get()->getAntiSpamOptions($group)->toArray();

        $options = [];
        foreach ($input['options_listed'] AS $optionId)
        {
            if (!array_key_exists($optionId, $antiSpamOptions))
            {
                continue;
            }

            if (!isset($input['options'][$optionId]))
            {
                $options[$optionId] = false;
            }
            else
            {
                $options[$optionId] = $input['options'][$optionId];
            }
        }

        Helper::repository(OptionRepo::class)->updateOptions($options);

        return $this->redirect($this->getDynamicRedirect());
    }

    protected function assertAntiSpamOptionGroupExists(array $with = [], ?string $phraseKey = null): OptionGroupEntity
    {
        $group = AntiSpamRepo::get()->getAntiSpamOptionGroup($with);
        if ($group === null)
        {
            $phraseKey = $phraseKey ?? 'requested_page_not_found';
            throw $this->exception($this->notFound(\XF::phrase($phraseKey)));
        }

        return $group;
    }

    protected function assertOptionExists(string $optionId): OptionEntity
    {
        $finder = AntiSpamRepo::get()->getAntiSpamOptionsFinder();

        /** @var OptionEntity|null  $option */
        $option = $finder->where('option_id', $optionId)->fetchOne();
        if ($option === null)
        {
            $phraseKey = $phraseKey ?? 'requested_page_not_found';
            throw $this->exception($this->notFound(\XF::phrase($phraseKey)));
        }

        return $option;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
