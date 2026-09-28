<?php

namespace DBTech\Credits\Admin\Controller;

use DBTech\Credits\Admin\View;
use DBTech\Credits\Entity\Currency;
use DBTech\Credits\Entity\Event;
use DBTech\Credits\Finder\TransactionFinder;
use DBTech\Credits\Repository\CurrencyRepository;
use DBTech\Credits\Repository\EventTriggerRepository;
use XF\Admin\Controller\AbstractController;
use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\InputFilterer;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\PrintableException;
use XF\Repository\NodeRepository;

class EventController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		$this->assertAdminPermission('dbtechCredits');
	}

	/**
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionIndex(): AbstractReply
	{
		$currencies = \XF::app()->repository(CurrencyRepository::class)
			->getCurrencyTitlePairs()
		;
		if (!count($currencies))
		{
			throw $this->exception($this->error(\XF::phrase('dbtech_credits_please_create_at_least_one_currency_before_continuing')));
		}

		$currencyId = $this->filter('currency_id', InputFilterer::UNSIGNED);
		if ($currencyId)
		{
			$currencyId = isset($currencies[$currencyId]) ? $currencyId : 0;
		}

		if (!$currencyId)
		{
			$currencyIds = array_keys($currencies);
			$currencyId = array_shift($currencyIds);
		}

		$events = \XF::app()->repository(EventTriggerRepository::class)
			->findEventsForList()
			->where('currency_id', $currencyId)
			->fetch()
		;

		$viewParams = [
			'events' => $events,
			'currency' => $currencyId,
			'currencies' => $currencies,
		];
		return $this->view(
			View\Event\ListingView::class,
			'dbtech_credits_event_list',
			$viewParams
		);
	}

	/**
	 * @param Event $event
	 * @return AbstractReply
	 */
	protected function eventAddEdit(Event $event): AbstractReply
	{
		$nodeRepo = \XF::app()->repository(NodeRepository::class);
		$nodeTree = $nodeRepo->createNodeTree($nodeRepo->getFullNodeList());

		$viewParams = [
			'event' => $event,
			'nodeTree' => $nodeTree,
		];
		return $this->view(
			View\Event\EditView::class,
			'dbtech_credits_event_edit',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionEdit(ParameterBag $params): AbstractReply
	{
		/** @var Event $event */
		$event = $this->assertEventExists($params->event_id);
		return $this->eventAddEdit($event);
	}

	/**
	 * @return AbstractReply
	 * @throws \Exception
	 */
	public function actionAdd(): AbstractReply
	{
		$eventTriggerId = $this->filter('event_trigger_id', InputFilterer::STRING);

		if ($eventTriggerId)
		{
			$eventTrigger = \XF::app()->repository(EventTriggerRepository::class)
				->getHandler($eventTriggerId)
			;
			if ($eventTrigger)
			{
				$event = \XF::app()->em()->create(Event::class);
				$event->event_trigger_id = $eventTriggerId;

				return $this->eventAddEdit($event);
			}
		}

		$viewParams = [
			'eventTriggers' => \XF::app()->repository(EventTriggerRepository::class)
				->getEventTriggerTitlePairs(true),
		];

		return $this->view(
			View\Event\AddChooserView::class,
			'dbtech_credits_event_add_chooser',
			$viewParams
		);
	}

	/**
	 * @param Event $event
	 *
	 * @return FormAction
	 * @throws \Exception
	 */
	protected function eventSaveProcess(Event $event): FormAction
	{
		$form = $this->formAction();

		$input = $this->filter([
			'title' => InputFilterer::STRING,
			'active' => InputFilterer::BOOLEAN,
			'currency_id' => InputFilterer::UNSIGNED,
			'event_trigger_id' => InputFilterer::STRING,

			'charge' => InputFilterer::BOOLEAN,
			'moderate' => InputFilterer::BOOLEAN,
			'main_add' => InputFilterer::FLOAT,
			'main_sub' => InputFilterer::FLOAT,
			'mult_add' => InputFilterer::FLOAT,
			'mult_sub' => InputFilterer::FLOAT,
			'frequency' => InputFilterer::UNSIGNED,
			'maxtime' => InputFilterer::UNSIGNED,
			'applymax' => InputFilterer::UNSIGNED,
			'applymax_peruser' => InputFilterer::BOOLEAN,
			'upperrand' => InputFilterer::FLOAT,
			'multmin' => InputFilterer::FLOAT,
			'multmax' => InputFilterer::FLOAT,
			'minaction' => InputFilterer::UNSIGNED,
			'owner' => InputFilterer::UNSIGNED,
			'curtarget' => InputFilterer::UNSIGNED,
			'alert' => InputFilterer::BOOLEAN,
			'display' => InputFilterer::BOOLEAN,

			'settings' => InputFilterer::ARRAY,
		]);

		$usableUserGroups = $this->filter('usable_user_group', InputFilterer::STRING);
		if ($usableUserGroups == 'all')
		{
			$input['user_group_ids'] = [-1];
		}
		else
		{
			$input['user_group_ids'] = $this->filter('usable_user_group_ids', 'array-uint');
		}

		$usableForums = $this->filter('node_ids', 'array-int');
		if (in_array(-1, $usableForums) || empty($usableForums))
		{
			$input['node_ids'] = [-1];
		}
		else
		{
			$input['node_ids'] = $usableForums;
		}

		$eventTrigger = \XF::app()->repository(EventTriggerRepository::class)
			->getHandler($input['event_trigger_id'])
		;
		$input['settings'] = $eventTrigger->filterOptions($input['settings']);

		$form->basicEntitySave($event, $input);

		return $form;
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws PrintableException
	 * @throws \Exception
	 */
	public function actionSave(ParameterBag $params): AbstractReply
	{
		$this->assertPostOnly();

		if ($params->event_id)
		{
			/** @var Event $event */
			$event = $this->assertEventExists($params->event_id);
		}
		else
		{
			$event = \XF::app()->em()->create(Event::class);
		}

		$this->eventSaveProcess($event)->run();

		return $this->redirect($this->buildLink('dbtech-credits/events') . $this->buildLinkHash($event->event_id));
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionDelete(ParameterBag $params): AbstractReply
	{
		$event = $this->assertEventExists($params['event_id']);

		$transactions = \XF::app()->finder(TransactionFinder::class)
			->where('event_id', $event->event_id)
		;

		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$event,
			$this->buildLink('dbtech-credits/events/delete', $event),
			$this->buildLink('dbtech-credits/events/edit', $event),
			$this->buildLink('dbtech-credits/events'),
			$event->title,
			'dbtech_credits_event_delete',
			['numTransactions' => $transactions->total()]
		);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionToggle(): AbstractReply
	{
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(Event::class);
	}

	/**
	 * @param int|null $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return Event
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertEventExists(?int $id, array $with = [], ?string $phraseKey = null): Event
	{
		return $this->assertRecordExists(Event::class, $id, $with, $phraseKey);
	}

	/**
	 * @param int|null $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return Currency
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertCurrencyExists(?int $id, array $with = [], ?string $phraseKey = null): Currency
	{
		return $this->assertRecordExists(Currency::class, $id, $with, $phraseKey);
	}
}