<?php

namespace DBTech\Shop\Service\Item;

use DBTech\Shop\Entity\Category;

/**
 * Class Create
 *
 * @package DBTech\Shop\Service\Item
 */
class Create extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/** @var \DBTech\Shop\Entity\Category  */
	protected $category;

	/** @var \DBTech\Shop\Entity\Item */
	protected $item;
	
	/** @var string */
	protected $itemType;
	
	/** @var string */
	protected $tagline;
	
	/** @var \DBTech\Shop\Service\Item\MessagePreparer */
	protected $descriptionPreparer;
	
	/** @var array */
	protected $availableFilters;
	
	/** @var array */
	protected $adminConfig;

	/** @var \XF\Service\Thread\Creator|null */
	protected $threadCreator;

	/** @var bool */
	protected $logIp = true;

	/** @var \XF\Service\Tag\Changer */
	protected $tagChanger;

	/** @var bool */
	protected $performValidations = true;
	
	/**
	 * Create constructor.
	 *
	 * @param \XF\App $app
	 * @param Category $category
	 * @param string $itemType
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct(\XF\App $app, Category $category, $itemType)
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
	protected function setupDefaults()
	{
		$item = $this->category->getNewItem($this->itemType);

		$this->item = $item;
		
		$this->descriptionPreparer = $this->service('DBTech\Shop:Item\MessagePreparer', $this->item, 'description', $this);
		
		$this->tagChanger = $this->service('XF:Tag\Changer', 'dbtech_shop_item', $this->category);

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
	 * @return \DBTech\Shop\Entity\Item
	 */
	public function getItem(): \DBTech\Shop\Entity\Item
	{
		return $this->item;
	}

	/**
	 * @param bool $perform
	 *
	 * @return $this
	 */
	public function setPerformValidations(bool $perform): Create
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
	public function setIsAutomated(): Create
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
	public function setTagLine(string $tagline): Create
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
	public function setPrefix(int $prefixId): Create
	{
		$this->item->prefix_id = $prefixId;

		return $this;
	}

	/**
	 * @param string $tags
	 *
	 * @return $this
	 */
	public function setTags(string $tags): Create
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
	public function setAvailableFilters(array $availableFilters): Create
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
	public function setAdminConfig(array $adminConfig): Create
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
	public function setItemFields(array $itemFields): Create
	{
		$item = $this->item;
		
		/** @var \XF\CustomField\Set $fieldSet */
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
	public function setDuration(string $type, int $amount, string $unit): Create
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
	public function logIp(bool $logIp): Create
	{
		$this->logIp = $logIp;

		return $this;
	}

	/**
	 *
	 */
	public function checkForSpam()
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

		/** @var \DBTech\Shop\Entity\Item $item */
		$item = $this->item;

		if (!$item->user_id)
		{
			/** @var \XF\Validator\Username $validator */
			$validator = $this->app->validator('Username');
			$item->username = $validator->coerceValue($item->username);

			if ($this->performValidations && !$validator->isValid($item->username, $error))
			{
				return [
					$validator->getPrintableErrorValue($error)
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
			) {
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
	 * @return \DBTech\Shop\Entity\Item
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	protected function _save(): \DBTech\Shop\Entity\Item
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
	 * @param \XF\Entity\Forum $forum
	 *
	 * @return \XF\Service\Thread\Creator
	 */
	protected function setupItemThreadCreation(\XF\Entity\Forum $forum): \XF\Service\Thread\Creator
	{
		/** @var \XF\Service\Thread\Creator $creator */
		$creator = $this->service('XF:Thread\Creator', $forum);
		$creator->setIsAutomated();

		$creator->setContent($this->item->getExpectedThreadTitle(), $this->getThreadMessage(), false);
		$creator->setPrefix($this->category->thread_prefix_id);

		$thread = $creator->getThread();
		$thread->bulkSet([
			'discussion_type' => 'dbtech_shop_item',
//			'discussion_state' => $this->item->item_state
			'discussion_state' => $this->item->active ? 'visible' : 'deleted'
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
			'item_link' => $this->app->router('public')->buildLink('canonical:dbtech-shop', $item),
		];

		$phraseParams['description'] = $this->app->bbCode()->render(
			$item->description,
			'bbCodeClean',
			'post',
			null
		);

		$phrase = \XF::phrase('dbtech_shop_item_thread_create', $phraseParams);

		return $phrase->render('raw');
	}

	/**
	 * @param \XF\Entity\Thread $thread
	 */
	protected function afterItemThreadCreated(\XF\Entity\Thread $thread)
	{
		/** @var \XF\Repository\Thread $threadRepo */
		$threadRepo = $this->repository('XF:Thread');
		$threadRepo->markThreadReadByVisitor($thread);

		/** @var \XF\Repository\ThreadWatch $threadWatchRepo */
		$threadWatchRepo = $this->repository('XF:ThreadWatch');
		$threadWatchRepo->autoWatchThread($thread, \XF::visitor(), true);
	}

	/**
	 *
	 */
	public function sendNotifications()
	{
		/*
		if ($this->item->isVisible())
		{
			$notifier = $this->service('DBTech\Shop:Item\Notify', $this->item);
			$notifier->notifyAndEnqueue(3);
		}
		*/

		if ($this->threadCreator)
		{
			$this->threadCreator->sendNotifications();
		}
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
	public function afterInsert()
	{
		$category = $this->category;
		$item = $this->item;

		$tagline = $item->getMasterTaglinePhrase();
		$tagline->phrase_text = $this->tagline ?: '';
		$tagline->save();
		
		if ($this->availableFilters)
		{
			$this->associateItemFilters($this->availableFilters);
		}

		if (
			$category->thread_node_id
			&& $category->ThreadForum
		) {
			$creator = $this->setupItemThreadCreation($category->ThreadForum);
			if ($creator && $creator->validate())
			{
				$thread = $creator->save();
				$item->fastUpdate('discussion_thread_id', $thread->thread_id);
				$this->threadCreator = $creator;

				$this->afterItemThreadCreated($thread);
			}
		}

		if ($this->logIp)
		{
			$ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
			$this->writeIpLog($ip);
		}
		
		/** @var \XF\Spam\ContentChecker $checker */
		$checker = $this->app->spam()->contentChecker();
		$checker->logContentSpamCheck('dbtech_shop_item', $item->item_id);
		$checker->logSpamTrigger('dbtech_shop_item', $item->item_id);
	}
	
	/**
	 * @param array $filterIds
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function associateItemFilters(array $filterIds)
	{
		/** @var \DBTech\Shop\Repository\ItemFilterMap $repo */
		$repo = $this->repository('DBTech\Shop:ItemFilterMap');
		$repo->updateItemAssociations($this->item->item_id, $filterIds);
	}
	
	/**
	 * @param string $ip
	 * @throws \LogicException
	 */
	protected function writeIpLog(string $ip)
	{
		/** @var \DBTech\Shop\Entity\Item $item */
		$item = $this->item;

		/** @var \XF\Repository\Ip $ipRepo */
		$ipRepo = $this->repository('XF:Ip');
		$ipEnt = $ipRepo->logIp(\XF::visitor()->user_id, $ip, 'dbtech_shop_item', $item->item_id);
		if ($ipEnt)
		{
			$item->fastUpdate('ip_id', $ipEnt->ip_id);
		}
	}
}