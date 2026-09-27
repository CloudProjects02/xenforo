<?php

namespace DBTech\Shop\Service\Item;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Repository\ItemFilterMapRepository;
use DBTech\Shop\Repository\ItemRepository;
use XF\App;
use XF\CustomField\Set;
use XF\PrintableException;
use XF\Repository\IpRepository;
use XF\Service\AbstractService;
use XF\Service\Tag\ChangerService;
use XF\Service\ValidateAndSavableTrait;
use XF\Spam\ContentChecker;

class EditService extends AbstractService
{
	use ValidateAndSavableTrait;

	protected Item $item;
	protected MessagePreparerService $descriptionPreparer;
	protected ChangerService $tagChanger;
	protected ?string $tagline = null;
	protected ?array $availableFilters = null;
	protected bool $logIp = true;
	protected bool $performValidations = true;
	protected bool $alert = false;
	protected string $alertReason = '';


	/**
	 * @param App $app
	 * @param Item $item
	 */
	public function __construct(App $app, Item $item)
	{
		parent::__construct($app);
		$this->item = $item;
		$this->setupDefaults();
	}

	/**
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function setupDefaults(): void
	{
		$this->descriptionPreparer = \XF::app()->service(MessagePreparerService::class, $this->item, 'description', $this);

		$this->tagChanger = \XF::app()->service(ChangerService::class, 'dbtech_shop_item', $this->item);
		$this->tagChanger->setContentId($this->item->item_id);
	}

	/**
	 * @return Item
	 */
	public function getItem(): Item
	{
		return $this->item;
	}

	/**
	 * @param bool $perform
	 *
	 * @return $this
	 */
	public function setPerformValidations(bool $perform): EditService
	{
		$this->performValidations = $perform;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function getPerformValidations(): bool
	{
		return $this->performValidations;
	}

	/**
	 * @return $this
	 */
	public function setIsAutomated(): EditService
	{
		$this->logIp(false);
		$this->setPerformValidations(false);

		return $this;
	}

	/**
	 * @param string $tagline
	 *
	 * @return $this
	 */
	public function setTagLine(string $tagline): EditService
	{
		$this->tagline = $tagline;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getTagline(): string
	{
		return $this->tagline;
	}

	/**
	 * @param string $description
	 * @param bool $format
	 *
	 * @return bool
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 */
	public function setDescription(string $description, bool $format = true): bool
	{
		return $this->descriptionPreparer->setMessage($description, $format, $this->performValidations);
	}

	/**
	 * @param int $prefixId
	 *
	 * @return $this
	 */
	public function setPrefix(int $prefixId): EditService
	{
		$this->item->prefix_id = $prefixId;

		return $this;
	}

	/**
	 * @param string $tags
	 *
	 * @return $this
	 */
	public function setTags(string $tags): EditService
	{
		if ($this->tagChanger->canEdit() || !$this->performValidations)
		{
			$this->tagChanger->setEditableTags($tags);
		}

		return $this;
	}

	/**
	 * @param array $availableFilters
	 *
	 * @return $this
	 */
	public function setAvailableFilters(array $availableFilters): EditService
	{
		$this->availableFilters = $availableFilters;

		return $this;
	}

	/**
	 * @param array $adminConfig
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function setAdminConfig(array $adminConfig): EditService
	{
		$item = $this->item;
		$handler = $item->getHandler();

		$item->code = $handler->filterAdminConfig($adminConfig);

		return $this;
	}

	/**
	 * @param array $itemFields
	 *
	 * @return $this
	 */
	public function setItemFields(array $itemFields): EditService
	{
		$item = $this->item;

		$editMode = $item->getFieldEditMode();

		/** @var Set $fieldSet */
		$fieldSet = $item->item_fields;
		$fieldDefinition = $fieldSet->getDefinitionSet()
			->filterEditable($fieldSet, $editMode)
			->filterOnly($item->Category->field_cache);

		$itemFieldsShown = array_keys($fieldDefinition->getFieldDefinitions());

		if ($itemFieldsShown)
		{
			$fieldSet->bulkSet($itemFields, $itemFieldsShown);
		}

		return $this;
	}

	/**
	 * @param string $type
	 * @param int $amount
	 * @param string $unit
	 *
	 * @return $this
	 */
	public function setDuration(string $type, int $amount, string $unit): EditService
	{
		$item = $this->item;
		if ($type == 'permanent')
		{
			$item->length_amount = 0;
			$item->length_unit = 'day';
		}
		else
		{
			$item->length_amount = $amount;
			$item->length_unit = $unit;
		}

		return $this;
	}

	/**
	 * @param bool $logIp
	 *
	 * @return $this
	 */
	public function logIp(bool $logIp): EditService
	{
		$this->logIp = $logIp;

		return $this;
	}

	/**
	 * @param bool $alert
	 * @param string|null $reason
	 *
	 * @return $this
	 */
	public function setSendAlert(bool $alert, ?string $reason = null): EditService
	{
		$this->alert = $alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}

		return $this;
	}

	/**
	 *
	 */
	public function checkForSpam(): void
	{
		if ($this->item->item_state == 'visible' && \XF::visitor()->isSpamCheckRequired())
		{
			$this->descriptionPreparer->checkForSpam();
		}
	}

	/**
	 *
	 */
	protected function finalSetup(): void
	{
		$this->item->last_update = \XF::$time;
	}

	/**
	 * @return array
	 */
	protected function _validate(): array
	{
		$this->finalSetup();

		/** @var Item $item */
		$item = $this->item;

		$item->preSave();
		$errors = $item->getErrors();

		if ($this->performValidations)
		{
			if (!$item->prefix_id
				&& $item->Category->require_prefix
				&& $item->Category->getUsablePrefixes()
				&& !$item->canMove()
			)
			{
				$errors[] = \XF::phraseDeferred('please_select_a_prefix');
			}

			if ($this->tagChanger->canEdit())
			{
				$tagErrors = $this->tagChanger->getErrors();
				if ($tagErrors)
				{
					$errors = array_merge($errors, $tagErrors);
				}
			}
		}

		return $errors;
	}

	/**
	 * @return Item
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	protected function _save(): Item
	{
		/** @var Item $item */
		$item = $this->item;

		$db = $this->db();
		$db->beginTransaction();

		$this->beforeUpdate();
		$this->descriptionPreparer->beforeUpdate();

		$item->save(true, false);

		$this->afterUpdate();
		$this->descriptionPreparer->afterUpdate();

		if ($this->tagChanger->canEdit() && $this->tagChanger->tagsChanged())
		{
			$this->tagChanger->save($this->performValidations);
		}

		if ($item->isVisible() && $this->alert && $item->user_id != \XF::visitor()->user_id)
		{
			$itemRepo = \XF::app()->repository(ItemRepository::class);
			$itemRepo->sendModeratorActionAlert($this->item, 'edit', $this->alertReason);
		}

		$db->commit();

		return $item;
	}

	/**
	 * @return array
	 */
	protected function _getMentionedUserIds(): array
	{
		return $this->descriptionPreparer->getMentionedUserIds();
	}

	/**
	 *
	 */
	public function beforeUpdate()
	{
	}

	/**
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 */
	public function afterUpdate(): void
	{
		$item = $this->item;

		if ($this->tagline !== null)
		{
			$tagline = $item->getMasterTaglinePhrase();
			$tagline->phrase_text = $this->tagline;
			$tagline->save();
		}

		if ($this->availableFilters)
		{
			$this->associateItemFilters($this->availableFilters);
		}

		if ($this->logIp)
		{
			$ip = ($this->logIp === true ? \XF::app()->request()->getIp() : $this->logIp);
			$this->writeIpLog($ip);
		}

		/** @var ContentChecker $checker */
		$checker = \XF::app()->spam()->contentChecker();
		$checker->logContentSpamCheck('dbtech_shop_item', $item->item_id);
		$checker->logSpamTrigger('dbtech_shop_item', $item->item_id);
	}

	/**
	 * @param array $filterIds
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function associateItemFilters(array $filterIds): void
	{
		$repo = \XF::app()->repository(ItemFilterMapRepository::class);
		$repo->updateItemAssociations($this->item->item_id, $filterIds);
	}

	/**
	 * @param string $ip
	 * @throws \LogicException
	 */
	protected function writeIpLog(string $ip): void
	{
		/** @var Item $item */
		$item = $this->item;

		$ipRepo = \XF::app()->repository(IpRepository::class);
		$ipEnt = $ipRepo->logIp(\XF::visitor()->user_id, $ip, 'dbtech_shop_item', $item->item_id);
		if ($ipEnt)
		{
			$item->fastUpdate('ip_id', $ipEnt->ip_id);
		}
	}
}