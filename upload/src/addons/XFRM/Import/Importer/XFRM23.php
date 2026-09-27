<?php

namespace XFRM\Import\Importer;

use XF\Db\AbstractAdapter;
use XF\Entity\FeaturedContent;
use XF\Import\StepState;
use XFRM\Entity\ResourceItem;
use XFRM\Job\ResourceUpdateEmbedMetadata;

use function in_array, intval;

class XFRM23 extends XFRM22
{
	public static function getListInfo()
	{
		return [
			'target' => 'XenForo Resource Manager',
			'source' => 'XenForo Resource Manager 2.3',
		];
	}

	protected function validateVersion(AbstractAdapter $db, &$error)
	{
		$versionId = $db->fetchOne(
			"SELECT version_id
				FROM xf_addon
				WHERE addon_id = 'XFRM'"
		);
		if (!$versionId || intval($versionId) < 2030031)
		{
			$error = \XF::phrase('xfrm_you_may_only_import_from_xenforo_resource_manager_x', [
				'version' => '2.3',
			]);
			return false;
		}

		return true;
	}

	public function getSteps()
	{
		$steps = parent::getSteps();

		$steps = $this->extendSteps(
			$steps,
			[
				'title' => \XF::phrase('xfrm_featured_resources'),
				'depends' => ['resources'],
			],
			'featuredResources',
			'reviewVotes'
		);

		return $steps;
	}

	public function getStepEndFeaturedResources(): int
	{
		return $this->getMaxFeaturedContentIdForContentType('resource');
	}

	public function stepFeaturedResources(
		StepState $state,
		array $stepConfig,
		int $maxTime
	): StepState
	{
		return $this->getFeatureStepStateForContentType(
			'resource',
			$state,
			$stepConfig,
			$maxTime,
			function (ResourceItem $content, FeaturedContent $feature): void
			{
				$content->fastUpdate('featured', true);
			}
		);
	}

	protected function rewriteMessage(
		string $text,
		string $importType = 'post'
	): string
	{
		$text = parent::rewriteMessage($text, $importType);

		$text = $this->rewriteEmbeds($text);

		return $text;
	}

	public function getFinalizeJobs(array $stepsRun): array
	{
		$jobs = parent::getFinalizeJobs($stepsRun);

		if (in_array('resources', $stepsRun, true))
		{
			$jobs[] = [
				ResourceUpdateEmbedMetadata::class,
				['types' => ['embeds']],
			];
		}

		return $jobs;
	}
}
