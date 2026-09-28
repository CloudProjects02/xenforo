<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SignupAbuseBlocking\AdminSearch;

use SV\SignupAbuseBlocking\Repository\AntiSpam as AntiSpamRepo;
use XF\AdminSearch\AbstractHandler;
use XF\Entity\Option as OptionEntity;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Router;
use function assert;


class AntiSpamOption extends AbstractHandler
{
    public function getDisplayOrder(): int
    {
        return 10;
    }

    public function search($text, $limit, array $previousMatchIds = [])
    {
        $optFinder = AntiSpamRepo::get()->getAntiSpamOptionsFinder();

        $conditions = [
            [$optFinder->columnUtf8('option_id'), 'like', $optFinder->escapeLike($text, '%?%')]
        ];
        if ($previousMatchIds)
        {
            $conditions[] = ['option_id', $previousMatchIds];
        }

        $optFinder
            ->whereOr($conditions)
            ->order($optFinder->caseInsensitive('option_id'))
            ->limit($limit);

        return $optFinder->fetch();
    }

    public function getRelatedPhraseGroups(): array
    {
        return ['option', 'option_explain'];
    }

    public function getTemplateData(Entity $record)
    {
        /** @var OptionEntity $record */
        /** @var Router $router */
        $router = $this->app->container('router.admin');
        return [
            'link' => $router->buildLink('anti-spam/option', $record),
            'title' => $record->title,
            'extra' => $record->option_id
        ];
    }

    public function isSearchable(): bool
    {
        $visitor = \XF::visitor();
        return $visitor->hasAdminPermission('option') || $visitor->hasAdminPermission('svAntiSpam');
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
