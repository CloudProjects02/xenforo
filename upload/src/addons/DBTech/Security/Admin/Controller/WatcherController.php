<?php

namespace DBTech\Security\Admin\Controller;

use DBTech\Security\Admin\View;
use DBTech\Security\Entity\Watcher;
use DBTech\Security\Repository\WatcherRepository;
use XF\Admin\Controller\AbstractController;
use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\PrintableException;

class WatcherController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		$this->assertAdminPermission('dbtechSecurity');
	}

	/**
	 * @return AbstractReply
	 * @throws \Exception
	 */
	public function actionIndex(): AbstractReply
	{
		$watchers = \XF::app()->repository(WatcherRepository::class)
			->findWatchersForList()
			->fetch()
		;

		$viewParams = [
			'watchers' => $watchers,
		];
		return $this->view(
			View\Watcher\ListingView::class,
			'dbtech_security_watcher_list',
			$viewParams
		);
	}

	/**
	 * @param Watcher $watcher
	 * @return AbstractReply
	 */
	protected function watcherAddEdit(Watcher $watcher): AbstractReply
	{
		$viewParams = [
			'watcher' => $watcher,
		];
		return $this->view(
			View\Watcher\EditView::class,
			'dbtech_security_watcher_edit',
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
		/** @var Watcher $watcher */
		$watcher = $this->assertWatcherExists($params->watcher_id);
		return $this->watcherAddEdit($watcher);
	}

	/**
	 * @return AbstractReply
	 * @throws \Exception
	 */
	public function actionAdd(): AbstractReply
	{
		$watcherType = $this->filter('watcher_type', 'str');

		if ($watcherType)
		{
			$handler = \XF::app()->repository(WatcherRepository::class)
				->getHandler($watcherType)
			;
			if ($handler)
			{
				$watcher = \XF::app()->em()->create(Watcher::class);
				$watcher->watcher_type = $watcherType;

				return $this->watcherAddEdit($watcher);
			}
		}

		$viewParams = [
			'watcherTypes' => \XF::app()->repository(WatcherRepository::class)
				->getWatcherTitlePairs(true),
		];
		return $this->view(
			View\Watcher\AddChooserView::class,
			'dbtech_security_watcher_add_chooser',
			$viewParams
		);
	}

	/**
	 * @param Watcher $watcher
	 *
	 * @return FormAction
	 * @throws \Exception
	 */
	protected function watcherSaveProcess(Watcher $watcher): FormAction
	{
		$form = $this->formAction();

		$input = $this->filter([
			'active' => 'bool',
			'watcher_type' => 'str',
			'priority' => 'uint',
			'actions' => [
				'closeForum' => 'bool',
				'emailWebmaster' => 'bool',
				'banIp' => 'bool',
				'banUser' => 'bool',
				'emailUser' => 'bool',
				'lockChange' => 'bool',
				'lockReset' => 'bool',
				'lockUser' => 'bool',
				'adminLockUser' => 'bool',
			],
		]);

		$watcherType = \XF::app()->repository(WatcherRepository::class)
			->getHandler($input['watcher_type'])
		;
		$input['rule_data'] = $watcherType->filterOptions($this->filter('rules', 'array'));

		$form->basicEntitySave($watcher, $input);

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

		if ($params->watcher_id)
		{
			/** @var Watcher $watcher */
			$watcher = $this->assertWatcherExists($params->watcher_id);
		}
		else
		{
			$watcher = \XF::app()->em()->create(Watcher::class);
		}

		$this->watcherSaveProcess($watcher)->run();

		return $this->redirect($this->buildLink('dbtech-security/watchers') . $this->buildLinkHash($watcher->watcher_id));
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionDelete(ParameterBag $params): AbstractReply
	{
		$watcher = $this->assertWatcherExists($params->watcher_id);

		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$watcher,
			$this->buildLink('dbtech-security/watchers/delete', $watcher),
			$this->buildLink('dbtech-security/watchers/edit', $watcher),
			$this->buildLink('dbtech-security/watchers'),
			$watcher->title
		);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionToggle(): AbstractReply
	{
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(Watcher::class);
	}

	/**
	 * @param int|null $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return Watcher
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertWatcherExists(?int $id, array $with = [], ?string $phraseKey = null): Watcher
	{
		return $this->assertRecordExists(Watcher::class, $id, $with, $phraseKey);
	}
}