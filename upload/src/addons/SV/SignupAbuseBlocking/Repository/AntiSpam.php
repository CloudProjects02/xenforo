<?php

namespace SV\SignupAbuseBlocking\Repository;

use SV\StandardLib\Helper;
use XF\Entity\Option as OptionEntity;
use XF\Entity\OptionGroup as OptionGroupEntity;
use XF\Entity\OptionGroupRelation as OptionGroupRelationEntity;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Repository\Option as OptionRepo;
use function in_array;
use function is_string;
use function strlen;
use function trim;


class AntiSpam extends Repository
{
    public const ANTI_SPAM_OPTIONS_GROUP = 'svSignupAbuseBlocking';

    public static function get() : self
    {
        return Helper::repository(self::class);
    }

    public function getAntiSpamOptionGroup(array $with = []): ?OptionGroupEntity
    {
        return Helper::find(OptionGroupEntity::class, static::ANTI_SPAM_OPTIONS_GROUP, $with);
    }

    public function getAntiSpamOptionsFinder(?OptionGroupEntity $group = null): Finder
    {
        $group = $group ?? $this->getAntiSpamOptionGroup();
        if ($group === null)
        {
            throw new \LogicException('Anti-spam options group is missing');
        }
        $displayOrder = "Relations|$group->group_id.display_order";

        return Helper::repository(OptionRepo::class)
                    ->findOptionsInGroup($group)
                    ->where($displayOrder, '>=', 400)
                    ->whereOr([
                        [$displayOrder, '<', 1000],
                        [$displayOrder, '>=', 2000]
                    ]);
    }

    public function getAntiSpamOptions(?OptionGroupEntity $group = null): AbstractCollection
    {
        return $this->getAntiSpamOptionsFinder($group)->fetch();
    }

    public function isAntiSpamOption(OptionEntity $option): bool
    {
        /** @var OptionGroupRelationEntity $group */
        $group = $option->Relations[static::ANTI_SPAM_OPTIONS_GROUP] ?? null;

        if ($group === null)
        {
            return false;
        }

        if ($group->display_order >= 400 &&
            ($group->display_order < 1000 || $group >= 2000))
        {
            return true;
        }

        return false;
    }

    public function getSpammableCustomFields(UserEntity $user, array $groups): array
    {
        $profile = $user->Profile;
        if ($profile === null)
        {
            return [];
        }

        $content = [];
        $customFields = $user->Profile->custom_fields;
        $spammableFieldTypes = $this->getSpammableCustomFieldTypes();

        $definitionSet = $customFields->getDefinitionSet()
                                      ->addFilter('svSpamCheck', function (array $field) use ($spammableFieldTypes) {
                                          return in_array($field['field_type'], $spammableFieldTypes, true);
                                      })->filter('svSpamCheck');
        if (count($groups) !== 0)
        {
            $definitionSet = $definitionSet->filterGroup($groups);
        }

        $definitionSet = $definitionSet->filterEditable($customFields, 'user');

        foreach ($customFields->getFieldValues() AS $fieldId => $value)
        {
            $definition = $definitionSet[$fieldId] ?? null;
            if ($definition === null)
            {
                continue;
            }

            if (!is_string($value) || trim($value) === '')
            {
                continue;
            }

            $content[$fieldId] = $value;
        }

        return $content;
    }

    public function getSpammableProfileFields(UserEntity $user, bool $isEdit): array
    {
        $profile = $user->Profile;
        if ($profile === null)
        {
            return [];
        }

        $key = $isEdit ? 'checkForSpamPhrasesProfileEdit' : 'checkForSpamPhrases';

        $fields = [];
        foreach ($profile->structure()->columns AS $column => $columnData)
        {
            $includeInSpamCheck = $columnData[$key] ?? false;

            if (!$includeInSpamCheck)
            {
                continue;
            }

            $value = $profile->get($column);
            if (!is_string($value))
            {
                continue;
            }

            $value = trim($value);
            if (strlen($value) !== 0)
            {
                $fields[$column] = $value;
            }
        }

        return $fields;
    }

    /**
     * @return string[]
     */
    public function getSpammableCustomFieldTypes(): array
    {
        return ['textbox', 'textarea', 'bbcode'];
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
