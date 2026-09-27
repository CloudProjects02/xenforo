<?php

namespace DBTech\Shop\Service\Item;

use DBTech\Shop\Entity\Category;
use DBTech\Shop\Entity\Item;
use DBTech\Shop\Repository\ItemFilterMapRepository;
use XF\App;
use XF\CustomField\Set;
use XF\Entity\Forum;
use XF\Entity\Thread;
use XF\PrintableException;
use XF\Repository\IpRepository;
use XF\Repository\ThreadRepository;
use XF\Repository\ThreadWatchRepository;
use XF\Service\AbstractService;
use XF\Service\Tag\ChangerService;
use XF\Service\Thread\CreatorService;
use XF\Service\ValidateAndSavableTrait;
use XF\Spam\ContentChecker;
use XF\Validator\Username;

class CreateService extends AbstractService
{
	use ValidateAndSavableTrait;

	protected Category $category;
	protected Item $item;
	protected string $itemType;
	protected MessagePreparerService $descriptionPreparer;
	protected ChangerService $tagChanger;
	protected string $tagline = '';
	protected ?array $availableFilters = null;
	protected ?CreatorService $threadCreator = null;
	protected bool $logIp = true;
	protected bool $performValidations = true;

	/**
	 * @param App $app
	 * @param Category $category
	 * @param string $itemType
	 */
	public function __construct(App $app, Category $category, string $itemType)
	{
		parent::__construct($app);
		$this->category = $category;
		$this->itemType = $itemType;
		$this->setupDefaults();
	}

	/**
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function setupDefaults(): void
	{
		$item = $this->category->getNewItem($this->itemType);
		$this->item = $item;

		$this->descriptionPreparer = \XF::app()->service(MessagePreparerService::class, $this->item, 'description', $this);

		$this->tagChanger = \XF::app()->service(ChangerService::class, 'dbtech_shop_item', $this->category);

		$visitor = \XF::visitor();
		$this->item->user_id = $visitor->user_id;
		$this->item->username = $visitor->username;

		$this->item->item_state = $this->category->getNewContentState();
	}

	/**
	 * @return Category
	 */
	public function getCategory(): Category
	{
		return $this->category;
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
	public function setPerformValidations(bool $perform): CreateService
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
	public function setIsAutomated(): CreateService
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
	public function setTagLine(string $tagline): CreateService
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
	public function setPrefix(int $prefixId): CreateService
	{
		$this->item->prefix_id = $prefixId;

		return $this;
	}

	/**
	 * @param string $tags
	 *
	 * @return $this
	 */
	public function setTags(string $tags): CreateService
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
	public function setAvailableFilters(array $availableFilters): CreateService
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
	public function setAdminConfig(array $adminConfig): CreateService
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
	public function setItemFields(array $itemFields): CreateService
	{
		$item = $this->item;

		/** @var Set $fieldSet */
		$fieldSet = $item->item_fields;
		$fieldDefinition = $fieldSet->getDefinitionSet()
			->filterEditable($fieldSet, 'user')
			->filterOnly($this->category->field_cache);

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
	public function setDuration(string $type, int $amount, string $unit): CreateService
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
	public function logIp(bool $logIp): CreateService
	{
		$this->logIp = $logIp;

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
	protected function finalSetup()
	{
	}

	/**
	 * @return array
	 */
	protected function _validate(): array
	{
		$this->finalSetup();

		/** @var Item $item */
		$item = $this->item;

		if (!$item->user_id)
		{
			/** @var Username $validator */
			$validator = \XF::app()->validator('Username');
			$item->username = $validator->coerceValue($item->username);

			if ($this->performValidations && !$validator->isValid($item->username, $error))
			{
				return [
					$validator->getPrintableErrorValue($error),
				];
			}
		}

		$item->preSave();
		$errors = $item->getErrors();

		if ($this->performValidations)
		{
			if (!$item->prefix_id
				&& $this->category->require_prefix
				&& $this->category->getUsablePrefixes()
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
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	protected function _save(): Item
	{
		$item = $this->item;

		$db = $this->db();
		$db->beginTransaction();

		$this->beforeInsert();
		$this->descriptionPreparer->beforeInsert();

		$item->save(true, false);

		$this->afterInsert();
		$this->descriptionPreparer->afterInsert();

		if ($this->tagChanger->canEdit())
		{
			$this->tagChanger
				->setContentId($item->item_id, true)
				->save($this->performValidations);
		}

		$db->commit();

		return $item;
	}

	/**
	 * @param Forum $forum
	 *
	 * @return CreatorService
	 */
	protected function setupItemThreadCreation(Forum $forum): CreatorService
	{
		$creator = \XF::app()->service(CreatorService::class, $forum);
		$creator->setIsAutomated();

		$creator->setContent($this->item->getExpectedThreadTitle(), $this->getThreadMessage(), false);
		$creator->setPrefix($this->category->thread_prefix_id);

		$thread = $creator->getThread();
		$thread->bulkSet([
			'discussion_type' => 'dbtech_shop_item',
			//			'discussion_state' => $this->item->item_state
			'discussion_state' => $this->item->active ? 'visible' : 'deleted',
		]);

		return $creator;
	}

	/**
	 * @return string
	 */
	protected function getThreadMessage(): string
	{
		$item = $this->item;

		$phraseParams = [
			'title' => $item->title,
			'tag_line' => $item->tagline,
			'username' => $item->User ? $item->User->username : $item->username,
			'item_link' => \XF::app()->router('public')->buildLink('canonical:dbtech-shop', $item),
		];

		$phraseParams['description'] = \XF::app()->bbCode()->render(
			$item->description,
			'bbCodeClean',
			'post',
			null
		);

		$phrase = \XF::phrase('dbtech_shop_item_thread_create', $phraseParams);

		return $phrase->render('raw');
	}

	/**
	 * @param Thread $thread
	 */
	protected function afterItemThreadCreated(Thread $thread): void
	{
		$threadRepo = \XF::app()->repository(ThreadRepository::class);
		$threadRepo->markThreadReadByVisitor($thread);

		$threadWatchRepo = \XF::app()->repository(ThreadWatchRepository::class);
		$threadWatchRepo->autoWatchThread($thread, \XF::visitor(), true);
	}

	/**
	 *
	 */
	public function sendNotifications(): void
	{
		/*
		if ($this->item->isVisible())
		{
			$notifier = \XF::app()->service(\DBTech\Shop\Service\Item\Notify::class, $this->item);
			$notifier->notifyAndEnqueue(3);
		}
		*/

		$this->threadCreator?->sendNotifications();
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
	public function beforeInsert()
	{
	}

	/**
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 */
	public function afterInsert(): void
	{
		$category = $this->category;
		$item = $this->item;

		$tagline = $item->getMasterTaglinePhrase();
		$tagline->phrase_text = $this->tagline ?: '';
		$tagline->save();

		if ($this->availableFilters !== null)
		{
			$this->associateItemFilters($this->availableFilters);
		}

		if (
			$category->thread_node_id
			&& $category->ThreadForum
		)
		{
			$creator = $this->setupItemThreadCreation($category->ThreadForum);
			if ($creator->validate())
			{
				$thread = $creator->save();
				$item->fastUpdate('discussion_thread_id', $thread->thread_id);
				$this->threadCreator = $creator;

				$this->afterItemThreadCreated($thread);
			}
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