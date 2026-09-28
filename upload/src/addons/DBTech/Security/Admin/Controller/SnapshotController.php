<?php

namespace DBTech\Security\Admin\Controller;

use DBTech\Security\Admin\View;
use DBTech\Security\Entity\Snapshot;
use DBTech\Security\Repository\BackupRepository;
use XF\Admin\Controller\AbstractController;
use XF\ControllerPlugin\DeletePlugin;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\PrintableException;

class SnapshotController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		$this->assertAdminPermission('dbtechSecurity');
	}

	/**
	 * @return AbstractReply
	 */
	public function actionIndex(): AbstractReply
	{
		$viewParams = [
			'snapshots' => \XF::app()->repository(BackupRepository::class)
				->findSnapshotsForList()
				->fetch(),
		];
		return $this->view(
			View\Snapshot\ListingView::class,
			'dbtech_security_snapshot_list',
			$viewParams
		);
	}

	/**
	 * @return AbstractReply
	 * @throws PrintableException
	 */
	public function actionBackup(): AbstractReply
	{
		$snapshotId = \XF::app()->repository(BackupRepository::class)
			->backupOptions()
		;

		return $this->redirect(
			$this->buildLink('dbtech-security/settings-backups') . $this->buildLinkHash($snapshotId),
			\XF::phrase('dbtech_security_backup_saved')
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionLoad(ParameterBag $params): AbstractReply
	{
		$snapshot = $this->assertSnapshotExists($params->snapshot_id);

		if ($this->isPost())
		{
			if ($snapshot->data)
			{
				\XF::app()->repository(BackupRepository::class)
					->updateOptions($snapshot->data)
				;
			}

			return $this->redirect(
				$this->buildLink('dbtech-security/settings-backups'),
				\XF::phrase('dbtech_security_backup_loaded')
			);
		}

		$viewParams = [
			'snapshot' => $snapshot,
		];
		return $this->view(
			View\Snapshot\LoadView::class,
			'dbtech_security_snapshot_load',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionDelete(ParameterBag $params): AbstractReply
	{
		$snapshot = $this->assertSnapshotExists($params->snapshot_id);

		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$snapshot,
			$this->buildLink('dbtech-security/settings-backups/delete', $snapshot),
			null,
			$this->buildLink('dbtech-security/settings-backups'),
			$snapshot->title
		);
	}

	/**
	 * @param string|null $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return Snapshot
	 * @throws Exception
	 */
	protected function assertSnapshotExists(?string $id, array $with = [], ?string $phraseKey = null): Snapshot
	{
		return $this->assertRecordExists(Snapshot::class, $id, $with, $phraseKey);
	}
}