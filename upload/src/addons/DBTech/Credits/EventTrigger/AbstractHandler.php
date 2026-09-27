<?php

namespace DBTech\Credits\EventTrigger;

use DBTech\Credits\Entity\Event as EventEntity;
use DBTech\Credits\Entity\Transaction as TransactionEntity;
use DBTech\Credits\Exception\SkipEventException;
use DBTech\Credits\Exception\StopEventTriggerException;
use DBTech\Credits\Finder\EventFinder;
use DBTech\Credits\Finder\TransactionFinder;
use DBTech\Credits\Repository\EventTriggerRepository;
use XF\App;
use XF\Entity\User;
use XF\InputFilterer;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Manager;
use XF\Phrase;
use XF\PreEscaped;
use XF\PrintableException;

abstract class AbstractHandler
{
	public const MULTIPLIER_NONE = 0x0000;
	public const MULTIPLIER_LABEL = 0x0001;
	public const MULTIPLIER_SIZE = 0x0002;
	public const MULTIPLIER_CURRENCY = 0x0003;

	/** @var array  */
	protected array $options = [
		'multiplier' => self::MULTIPLIER_NONE,
		'isGlobal' => false,
		'canRevert' => false,
		'canCancel' => false,
		'canRebuild' => false,
		'canCharge' => true,
		'useUserGroups' => true,
		'useOwner' => false,
		'improvedUndo' => false,
		'adminOverride' => false,
	];
	protected string $contentType;

	/** @var AbstractCollection<EventEntity>|null */
	protected ?AbstractCollection $events = null;


	/**
	 * @param string $contentType
	 */
	public function __construct(string $contentType)
	{
		$this->contentType = $contentType;

		$this->setupOptions();
	}

	/**
	 * Designed to be overridden if need be
	 */
	protected function setupOptions(): void
	{
	}

	/**
	 * @return array
	 */
	public function getOptions(): array
	{
		return $this->options;
	}

	/**
	 * @param string $key
	 *
	 * @return mixed|null
	 */
	public function getOption(string $key): mixed
	{
		return $this->options[$key] ?? null;
	}

	/**
	 * @param string $key
	 * @param $value
	 *
	 * @return $this
	 */
	public function setOption(string $key, $value): AbstractHandler
	{
		$this->options[$key] = $value;

		return $this;
	}

	/**
	 * @param bool $forView
	 *
	 * @return array
	 */
	public function getEntityWith(bool $forView = false): array
	{
		return [];
	}

	/**
	 * @param $id
	 *
	 * @return null|AbstractCollection|Entity
	 */
	public function getContent($id): Entity|AbstractCollection|null
	{
		return $this->findByContentType($this->contentType, $id, $this->getEntityWith());
	}

	/**
	 * @return string
	 */
	public function getContentType(): string
	{
		return $this->contentType;
	}

	/**
	 * @param User $user
	 * @param bool $negate
	 * @param array $extraParams
	 *
	 * @return \ArrayObject
	 */
	public function initExtraParams(
		User $user,
		bool $negate = false,
		array $extraParams = []
	): \ArrayObject
	{
		$extraParams = array_replace([
			'multiplier' => 1,
			'message' => '',
			'timestamp' => \XF::$time,
			'source_user_id' => $user->user_id,
			'owner_id' => 0,
			'content_type' => '',
			'content_id' => 0,
			'node_id' => 0,
			'currency_id' => 0,
			'event_id' => 0,
			'alwaysCheck' => false,
			'enableAlert' => true,
			'runPostSave' => true,
			'forceVisible' => false,
			'negate' => $negate,
		], $extraParams);
		$extraParams = new \ArrayObject($extraParams, \ArrayObject::ARRAY_AS_PROPS);

		// Override multiplier if needed
		if ($this->getOption('multiplier') == self::MULTIPLIER_SIZE)
		{
			$extraParams->multiplier = $this->contentSize($extraParams->multiplier);
		}

		return $extraParams;
	}

	/**
	 * @param AbstractCollection<EventEntity>|null $events
	 *
	 * @return $this
	 */
	public function setEvents(?AbstractCollection $events = null): AbstractHandler
	{
		$this->events = $events;

		return $this;
	}

	/**
	 * @param bool $force
	 *
	 * @return AbstractCollection<EventEntity>
	 */
	public function getEvents(bool $force = false): AbstractCollection
	{
		if ($this->events === null || $force)
		{
			/** @var AbstractCollection<EventEntity> $events */
			$events = \XF::app()->finder(EventFinder::class)
				->where('event_trigger_id', $this->getContentType())
				->fetch()
			;

			$this->events = $events;
		}

		return $this->events;
	}

	/**
	 * @param \ArrayObject $extraParams
	 * @param User $user
	 * @param mixed $refId
	 * @param bool $negate
	 *
	 * @return AbstractCollection<EventEntity>
	 * @throws PrintableException
	 */
	public function getApplicableEvents(
		\ArrayObject    $extraParams,
		User $user,
		mixed           $refId,
		bool            $negate = false
	): AbstractCollection
	{
		$options = \XF::app()->options();

		// Used by our own extensions like DB Donate and DB Shop
		$this->assertEventExists($extraParams->currency_id);

		/** @var AbstractCollection<EventEntity> $events */
		$events = $this->getEvents()
			->filter(function (EventEntity $event) use ($extraParams, $user): bool
			{
				if (!$event->isActive())
				{
					return false;
				}

				if (!$event->isValidForUser($user))
				{
					return false;
				}

				if ($extraParams->event_id && $event->event_id != $extraParams->event_id)
				{
					return false;
				}

				if ($extraParams->currency_id && $event->currency_id != $extraParams->currency_id)
				{
					return false;
				}

				if (!$event->Currency->isActive())
				{
					return false;
				}

				if (!$this->assertEvent($event, $user, $extraParams))
				{
					// Skip this
					return false;
				}

				return true;
			})
		;

		foreach ($events AS $event)
		{
			try
			{
				$amount = $event->getCalculatedAmount($this, $user, $extraParams);
			}
			catch (SkipEventException $e)
			{
				$events->offsetUnset($event->event_id);
				continue;
			}
			catch (StopEventTriggerException $e)
			{
				return \XF::app()->em()->getEmptyCollection();
			}

			if ($amount == 0 && (
				!$options->dbtechCreditsSmartNegate
					|| !$negate
					|| !$refId
			)
			)
			{
				$events->offsetUnset($event->event_id);
			}
		}

		if ($options->dbtech_credits_best_event)
		{
			$eventAmountsByCurrency = [];
			foreach ($events AS $event)
			{
				if (!isset($eventAmountsByCurrency[$event->currency_id]))
				{
					$eventAmountsByCurrency[$event->currency_id] = [];
				}

				$eventAmountsByCurrency[$event->currency_id][$event->event_id] = $event->getOption('calculated_amount');
			}

			$bestEvents = [];
			foreach ($eventAmountsByCurrency AS $currencyId => $eventAmounts)
			{
				if ($extraParams->negate)
				{
					// pick worst one
					asort($eventAmounts, SORT_NUMERIC);
				}
				else
				{
					// pick best one
					arsort($eventAmounts, SORT_NUMERIC);
				}

				$bestEvents[] = key($eventAmounts);
			}

			// Filter out the events that were not the best
			$events = $events->filter(function (EventEntity $event) use ($bestEvents, $user): bool
			{
				if (!in_array($event->event_id, $bestEvents))
				{
					return false;
				}

				return true;
			});
		}

		return $events;
	}

	/**
	 * @param string $contentType
	 * @param int|array $contentId
	 * @param array $with
	 *
	 * @return null|AbstractCollection|Entity
	 */
	public function findByContentType(string $contentType, int|array $contentId, array $with = []): Entity|AbstractCollection|null
	{
		$entity = $this->getContentTypeEntity($contentType);

		if (is_array($contentId))
		{
			return \XF::app()->em()->findByIds($entity, $contentId, $with);
		}
		else
		{
			return \XF::app()->em()->find($entity, $contentId, $with);
		}
	}

	/**
	 * @param string $contentType
	 * @param bool $throw
	 *
	 * @return string|null
	 */
	public function getContentTypeEntity(string $contentType, bool $throw = true): ?string
	{
		$entityId = \XF::app()->getContentTypeFieldValue($contentType, 'dbtech_credits_entity');
		if (!$entityId && $throw)
		{
			throw new \LogicException("Content type $contentType must define a 'dbtech_credits_entity' value");
		}

		return $entityId;
	}

	/**
	 * @return Phrase
	 */
	public function getTitle(): Phrase
	{
		return \XF::phrase('dbtech_credits_eventtrigger_title.' . $this->contentType);
	}

	/**
	 * @return Phrase
	 */
	public function getDescription(): Phrase
	{
		return \XF::phrase('dbtech_credits_eventtrigger_description.' . $this->contentType);
	}

	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return true;
	}

	/**
	 * @return int
	 */
	public function getMultiplier(): int
	{
		return $this->options['multiplier'];
	}


	public function getLabels(): array
	{
		$labels = [];

		if ($this->getMultiplier() == self::MULTIPLIER_SIZE)
		{
			if (\XF::app()->options()->dbtech_credits_size_words)
			{
				$labels['minimum_amount'] = \XF::phrase('dbtech_credits_minimum_words');
				$labels['maximum_amount'] = \XF::phrase('dbtech_credits_maximum_words');
				$labels['minimum_action'] = \XF::phrase('dbtech_credits_below_minimum_words');
				$labels['minimum_action_explain'] = \XF::phrase('dbtech_credits_below_minimum_words_explain');
				$labels['multiplier_addition'] = \XF::phrase('dbtech_credits_amount_per_word');
				$labels['multiplier_addition_explain'] = \XF::phrase('dbtech_credits_amount_per_word_explain');
				$labels['multiplier_negation'] = \XF::phrase('dbtech_credits_negation_amount_per_word');
				$labels['multiplier_negation_explain'] = \XF::phrase('dbtech_credits_negation_amount_per_word_explain');
			}
			else
			{
				$labels['minimum_amount'] = \XF::phrase('dbtech_credits_minimum_characters');
				$labels['maximum_amount'] = \XF::phrase('dbtech_credits_maximum_characters');
				$labels['minimum_action'] = \XF::phrase('dbtech_credits_below_minimum_characters');
				$labels['minimum_action_explain'] = \XF::phrase('dbtech_credits_below_minimum_characters_explain');
				$labels['multiplier_addition'] = \XF::phrase('dbtech_credits_amount_per_character');
				$labels['multiplier_addition_explain'] = \XF::phrase('dbtech_credits_amount_per_character_explain');
				$labels['multiplier_negation'] = \XF::phrase('dbtech_credits_negation_amount_per_character');
				$labels['multiplier_negation_explain'] = \XF::phrase('dbtech_credits_negation_amount_per_character_explain');
			}
		}

		return $labels;
	}

	/**
	 * @param mixed $refId
	 * @param array $extraParams
	 * @param User|null $user
	 *
	 * @return TransactionEntity[]
	 * @throws PrintableException
	 */
	public function apply(mixed $refId, array $extraParams = [], ?User $user = null): array
	{
		/** @var \DBTech\Credits\XF\Entity\User $user */
		$user = $user ?: \XF::visitor();

		return $this->trigger($user, $refId, false, $extraParams);
	}

	/**
	 * @param mixed $refId
	 * @param array $extraParams
	 * @param User|null $user
	 *
	 * @return TransactionEntity[]
	 * @throws PrintableException
	 */
	public function undo(mixed $refId, array $extraParams = [], ?User $user = null): array
	{
		/** @var \DBTech\Credits\XF\Entity\User $user */
		$user = $user ?: \XF::visitor();

		return $this->trigger($user, $refId, true, $extraParams);
	}

	/**
	 * @param array $extraParams
	 * @param User|null $user
	 *
	 * @return TransactionEntity[]
	 * @throws PrintableException
	 */
	public function testApply(array $extraParams = [], ?User $user = null): array
	{
		return $this->apply(null, $extraParams, $user);
	}

	/**
	 * @param array $extraParams
	 * @param User|null $user
	 *
	 * @return TransactionEntity[]
	 * @throws PrintableException
	 */
	public function testUndo(array $extraParams = [], ?User $user = null): array
	{
		return $this->undo(null, $extraParams, $user);
	}

	/**
	 * @param User $user
	 * @param mixed $refId
	 * @param bool $negate
	 * @param array $extraParams
	 *
	 * @return TransactionEntity[]
	 * @throws PrintableException
	 */
	protected function trigger(
		User $user,
		mixed           $refId,
		bool            $negate = false,
		array           $extraParams = []
	): array
	{
		$options = \XF::app()->options();

		if (!$options->dbtech_credits_enable_events)
		{
			return [];
		}

		// Do it in two steps so this can be called by external function calls
		$extraParams = $this->initExtraParams($user, $negate, $extraParams);
		$events = $this->getApplicableEvents($extraParams, $user, $refId, $negate);

		if (!$user->user_id)
		{
			return [];
		}

		/** @var TransactionEntity[] $queue */
		$queue = [];

		foreach ($events AS $event)
		{
			if ($options->dbtechCreditsSmartNegate
				&& $negate
				&& $refId
				&& $extraParams->content_type
				&& $extraParams->content_id
				&& $event->getOption('calculated_amount') == 0
			)
			{
				// We have enough information to look for an existing transaction

				/** @var AbstractCollection<TransactionEntity> $transactions */
				$transactions = \XF::app()->finder(TransactionFinder::class)
					->where('event_id', $event->event_id)
					->where('user_id', $user->user_id)
					->where('reference_id', $refId)
					->where('content_type', $extraParams->content_type)
					->where('content_id', $extraParams->content_id)
					->fetch()
				;
				if ($transactions->count() == 1)
				{
					$transactions->first()->delete();
				}

				continue;
			}

			$transaction = \XF::app()->em()->create(TransactionEntity::class);
			$transaction->transaction_state = 'visible';
			$transaction->event_id = $event->event_id;
			$transaction->event_trigger_id = $event->event_trigger_id;
			$transaction->currency_id = $event->currency_id;
			$transaction->user_id = $user->user_id;
			$transaction->source_user_id = $extraParams->source_user_id;
			$transaction->owner_id = $extraParams->owner_id;
			$transaction->dateline = $extraParams->timestamp;
			$transaction->amount = $event->getOption('calculated_amount');
			$transaction->reference_id = ((!is_bool($refId) && !is_null($refId)) ? $refId : '');
			$transaction->negate = $extraParams->negate;
			$transaction->node_id = $extraParams->node_id;
			$transaction->multiplier = $extraParams->multiplier;
			$transaction->message = $extraParams->message;
			$transaction->content_type = $extraParams->content_type;
			$transaction->content_id = $extraParams->content_id;

			if ($event->moderate && !$extraParams->forceVisible)
			{
				// Immediately set transaction_state to moderated so we don't need pro checks
				$transaction->transaction_state = 'moderated';
			}
			else if (!$extraParams->negate)
			{
				$this->performApplyTransactionChecks($event, $user, $extraParams, $transaction);
			}
			else
			{
				$this->performNegateTransactionChecks($event, $user, $extraParams, $transaction);
			}

			if ($transaction->amount > 0
				&& $transaction->transaction_state === 'visible'
				&& $expiry = $event->getSetting('expiry')
			)
			{
				$transaction->expiry_date = \XF::$time + ($expiry * 3600);
			}

			$queue[] = $transaction;
		}

		if ($queue && $refId !== null)
		{
			// Only commit if we have a refId

			$db = \XF::app()->db();
			$db->beginTransaction();

			foreach ($queue AS $i => $transaction)
			{
				// Make sure we toggle alerts when needed
				$transaction->setOption('enableAlert', $extraParams->enableAlert);

				if (!$transaction->save(true, false))
				{
					$db->rollback();
					return $queue;
				}

				if ($extraParams->runPostSave)
				{
					// Handle postSave
					$this->postSave($transaction);
				}

				unset($queue[$i]);
			}

			$db->commit();

			return $queue;
		}

		// Return all the transactions we otherwise would have committed
		return $queue;
	}

	protected function performApplyTransactionChecks(
		EventEntity $event,
		User $user,
		\ArrayObject $extraParams,
		TransactionEntity $transaction
	): void
	{
		// Init this
		$SQL = [];

		// Whether we need to check frequency
		$doFrequencyCheck = $event->frequency > 1;
		$doCurrencyCheck = $event->Currency->earnmax > 0;
		$doEventCheck = $event->applymax > 0;

		if ($doFrequencyCheck)
		{
			// We need to check for event frequency
			$SQL[] = 'SUM(event_id = ' . $event->event_id . '
						AND transaction_state = \'skipped\'
						AND dateline >= (
							SELECT IFNULL(MAX(dateline), 0)
							FROM xf_dbtech_credits_transaction
							WHERE event_id = ' . $event->event_id . '
								AND user_id = ' . $user->user_id . '
								AND transaction_state IN (\'visible\', \'moderated\') AND negate = 0)
						) AS skipped';
		}

		if ($doCurrencyCheck)
		{
			// We need to check for maximum
			$SQL[] = 'SUM(
						IF(currency_id = ' . $event->Currency->currency_id . ' 
							AND transaction_state IN (\'visible\', \'moderated\')' .
				($event->Currency->maxtime ? (' AND dateline >= ' . ($transaction->dateline - $event->Currency->maxtime)) : '') . ', 
						amount, 
						0)
					) AS earned';
		}

		if ($doEventCheck)
		{
			// We need to check for maximum event applications
			$SQL[] = 'SUM(event_id = ' . $event->event_id .
				($event->applymax_peruser ? (' AND user_id = ' . $user->user_id) : '') . '
						AND transaction_state IN (\'visible\', \'moderated\') 
						AND negate = 0' .
				($event->maxtime ? (' AND dateline >= ' . ($transaction->dateline - $event->maxtime)) : '') . '
					) AS times';
		}

		if (count($SQL))
		{
			$stats = \XF::app()->db()->fetchRow('
						SELECT ' . implode(', ', $SQL) . '
						FROM xf_dbtech_credits_transaction
						WHERE negate = 0
							AND user_id = ?
					', $user->user_id);

			if (
				(!$doCurrencyCheck || ($stats['earned'] + $transaction->amount) <= $event->Currency->earnmax)
				&& (!$doEventCheck || $stats['times'] < $event->applymax)
			)
			{
				// within maximums
				if ($doFrequencyCheck && ($stats['skipped'] + 1) < $event->frequency)
				{
					// The event has not been skipped enough times just yet
					$transaction->transaction_state = 'skipped';
				}
			}
			else
			{
				// exceeded maximums
				$transaction->transaction_state = 'skipped_maximum';
			}
		}
	}

	protected function performNegateTransactionChecks(
		EventEntity $event,
		User $user,
		\ArrayObject $extraParams,
		TransactionEntity $transaction
	): void
	{
		// Whether we need to check frequency
		$doFrequencyCheck = $event->frequency > 1;
		$doCurrencyCheck = $event->Currency->earnmax > 0;
		$doEventCheck = $event->applymax > 0;

		if ($doFrequencyCheck || $doCurrencyCheck || $doEventCheck)
		{
			/** @var TransactionEntity $previousTxn */
			$previousTxn = \XF::app()->finder(TransactionFinder::class)
				->where('event_id', $event->event_id)
				->where('user_id', $user->user_id)
				->where('source_user_id', $transaction->source_user_id)
				->order('transaction_id', 'DESC')
				->fetchOne()
			;

			// If the previous transaction was skipped, also skip the negation of that event
			$transaction->transaction_state = $previousTxn ? $previousTxn->transaction_state : 'visible';
		}
	}

	/**
	 * @param int $currencyId
	 */
	protected function assertEventExists(int $currencyId = 0)
	{
		// This will be overridden by child events
	}

	/**
	 * @param EventEntity $event
	 * @param User $user
	 * @param \ArrayObject $extraParams
	 *
	 * @return bool
	 */
	protected function assertEvent(EventEntity $event, User $user, \ArrayObject $extraParams): bool
	{
		if (
			!$this->getOption('isGlobal')
			&& count($event->node_ids)
			&& !in_array(-1, $event->node_ids)
			&& !in_array($extraParams->node_id, $event->node_ids)
		)
		{
			return false;
		}

		if (
			$this->getOption('useUserGroups')
			&& count($event->user_group_ids)
			&& !in_array(-1, $event->user_group_ids)
			&& !$user->isMemberOf($event->user_group_ids)
		)
		{
			return false;
		}

		if (
			$this->getOption('useOwner')
			&& (
				($event->owner == 1 && $user->user_id == $extraParams->owner_id)
				|| ($event->owner == 2 && $user->user_id != $extraParams->owner_id)
			)
		)
		{
			return false;
		}

		return true;
	}

	/**
	 * @param TransactionEntity $transaction
	 */
	protected function postSave(TransactionEntity $transaction): void
	{
	}

	/**
	 * @param TransactionEntity $transaction
	 */
	public function onReject(TransactionEntity $transaction): void
	{
	}

	/**
	 * @param int $lastId
	 * @param int $amount
	 *
	 * @return mixed
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public function rebuildRange(int $lastId, int $amount)
	{
		$entities = $this->getContentInRange($lastId, $amount);
		if (!$entities->count())
		{
			return false;
		}

		$this->rebuildEntities($entities);

		$keys = $entities->keys();
		return $keys ? max($keys) : false;
	}

	/**
	 * @param int $lastId
	 * @param int $amount
	 * @param bool $forView
	 *
	 * @return AbstractCollection
	 */
	public function getContentInRange(int $lastId, int $amount, bool $forView = false): AbstractCollection
	{
		$entityId = $this->getContentTypeEntity($this->contentType);

		$em = \XF::em();
		try
		{
			$key = \XF::app()->em()->getEntityStructure($entityId)->primaryKey;
		}
		catch (\LogicException $e)
		{
			return $em->getEmptyCollection();
		}

		if (is_array($key))
		{
			if (count($key) > 1)
			{
				throw new \LogicException("Entity $entityId must only have a single primary key");
			}
			$key = reset($key);
		}

		$finder = \XF::app()->em()->getFinder($entityId)
			->where($key, '>', $lastId)
			->order($key)
			->with($this->getEntityWith($forView));

		$this->applyFinderConstraints($finder);

		return $finder->fetch($amount);
	}

	/**
	 * @param AbstractCollection $entities
	 */
	public function rebuildEntities(AbstractCollection $entities): void
	{
		foreach ($entities AS $entity)
		{
			$this->rebuild($entity);
		}
	}

	/**
	 * @param Entity $entity
	 */
	public function rebuild(Entity $entity): void
	{
	}

	/**
	 * @param Finder $finder
	 *
	 * @return void
	 */
	protected function applyFinderConstraints(Finder $finder): void
	{
	}

	/**
	 * @param string $string
	 *
	 * @return int
	 */
	protected function contentSize(string $string): int
	{
		$stringFormatter = \XF::app()->stringFormatter();

		$string = preg_replace(
			'#\[(code|icode)[^\]]*\].*\[/\\1\]#siU',
			'',
			$string
		);
		$string = $stringFormatter->stripBbCode($string, [
			'stripQuote' => true,
		]);

		return count(\XF::app()->options()->dbtech_credits_size_words ? $this->splitWords($string) : $this->splitChars($string));
	}

	/**
	 * @param string $string
	 *
	 * @return string[]
	 */
	protected function splitWords(string $string): array
	{
		return preg_split('/(\s+)/', $string, 0, PREG_SPLIT_NO_EMPTY);
	}

	/**
	 * @param string $string
	 *
	 * @return string[]
	 */
	protected function splitChars(string $string): array
	{
		$characters = preg_split('/(.)/', $string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		return array_filter($characters, function ($v, $k): bool
		{
			if (empty(trim($v)))
			{
				return false;
			}

			return true;
		}, ARRAY_FILTER_USE_BOTH);
	}

	/**
	 * @param TransactionEntity $transaction
	 *
	 * @return string
	 */
	public function alertTemplate(TransactionEntity $transaction): string
	{
		return '';
	}

	/**
	 * @param EventEntity $event
	 *
	 * @return string
	 */
	public function renderOptions(EventEntity $event): string
	{
		$templateName = $this->getOptionsTemplate();
		if (!$templateName)
		{
			return '';
		}
		return \XF::app()->templater()->renderTemplate(
			$templateName,
			array_merge($this->getDefaultTemplateParams('options'), ['event' => $event])
		);
	}

	/**
	 * @return string|null
	 */
	public function getOptionsTemplate(): ?string
	{
		return 'admin:dbtech_credits_event_edit_' . $this->contentType;
	}

	/**
	 * @param array $input
	 *
	 * @return array
	 */
	public function filterOptions(array $input = []): array
	{
		return \XF::app()->inputFilterer()->filterArray($input, $this->getFilterOptions());
	}

	/**
	 * @return string[]
	 */
	protected function getFilterOptions(): array
	{
		return [
			'expiry' => InputFilterer::UNSIGNED,
		];
	}

	/**
	 * @param string $context
	 *
	 * @return array
	 */
	protected function getDefaultTemplateParams(string $context): array
	{
		return [
			'title' => $this->getTitle(),
			'options' => $this->options,
		];
	}

	/**
	 * @param string $phraseKey
	 * @param TransactionEntity $transaction
	 * @param array $params
	 *
	 * @return Phrase
	 */
	protected function getAlertPhrase(string $phraseKey, TransactionEntity $transaction, array $params = []): Phrase
	{
		$amount = abs($transaction->amount);
		$params = array_replace($params, [
			'currency' => new PreEscaped('<a href="' .
				\XF::app()->router()->buildLink('canonical:dbtech-credits/currency', $transaction->Currency) .
				'" class="fauxBlockLink-blockLink" data-xf-click="overlay">' .
					$transaction->Currency->getFormattedValue($amount) . ' ' .
					$transaction->Currency->title .
				'</a>'),
		]);

		return \XF::phrase($phraseKey, $params);
	}

	/**
	 * @param string $type
	 * @param bool $throw
	 *
	 * @return AbstractHandler|null
	 * @throws \Exception
	 */
	protected function getHandler(string $type, bool $throw = true): ?AbstractHandler
	{
		return \XF::app()->repository(EventTriggerRepository::class)
			->getHandler($type, $throw)
		;
	}

	/**
	 * @return \ArrayObject
	 */
	protected function options(): \ArrayObject
	{
		return \XF::app()->options();
	}

	/**
	 * @return Manager
	 */
	protected function em(): Manager
	{
		return \XF::app()->em();
	}

	/**
	 * @return App
	 */
	protected function app(): App
	{
		return \XF::app();
	}
}