<?php

namespace XF\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\AddOn\AddOn;

use function count;

class AddOnList extends AbstractCommand
{
	protected function configure()
	{
		$this
			->setName('xf:addon-list')
			->setAliases(['xf-addon:list'])
			->setDescription('Lists installed add-ons')
			->addOption(
				'active',
				null,
				InputOption::VALUE_NONE,
				'Only show active add-ons'
			)
			->addOption(
				'inactive',
				null,
				InputOption::VALUE_NONE,
				'Only show inactive add-ons'
			)
			->addOption(
				'json',
				null,
				InputOption::VALUE_NONE,
				'Output as JSON'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		$addOns = \XF::app()->addOnManager()->getInstalledAddOns();

		$showActive = $input->getOption('active');
		$showInactive = $input->getOption('inactive');

		if ($showActive && !$showInactive)
		{
			$addOns = array_filter($addOns, function ($addOn)
			{
				return $addOn->isActive();
			});
		}
		else if ($showInactive && !$showActive)
		{
			$addOns = array_filter($addOns, function ($addOn)
			{
				return !$addOn->isActive();
			});
		}

		$totalActive = 0;
		$totalInactive = 0;

		foreach ($addOns AS $addOn)
		{
			if ($addOn->isActive())
			{
				$totalActive++;
			}
			else
			{
				$totalInactive++;
			}
		}

		$total = count($addOns);

		if ($input->getOption('json'))
		{
			return $this->outputJson($output, $addOns, $total, $totalActive, $totalInactive);
		}

		if ($total === 0)
		{
			$io->warning('No add-ons found.');
			return 0;
		}

		$isVerbose = $output->isVerbose();

		$io->table(
			$this->getTableHeaders($isVerbose),
			$this->buildTableRows($addOns, $isVerbose)
		);

		$io->text(sprintf(
			'Total: %d %s (%d active, %d inactive)',
			$total,
			$total === 1 ? 'add-on' : 'add-ons',
			$totalActive,
			$totalInactive
		));

		return 0;
	}

	/**
	 * @param AddOn[] $addOns
	 */
	protected function outputJson(
		OutputInterface $output,
		array $addOns,
		int $total,
		int $totalActive,
		int $totalInactive
	): int
	{
		$jsonAddOns = [];

		foreach ($addOns AS $addOn)
		{
			$installed = $addOn->getInstalledAddOn();

			$jsonAddOns[] = [
				'addon_id' => $addOn->addon_id,
				'title' => $addOn->title,
				'version_string' => $addOn->version_string,
				'version_id' => $addOn->version_id,
				'active' => $addOn->isActive(),
				'legacy' => $addOn->isLegacy(),
				'is_processing' => (bool) $installed->is_processing,
				'last_pending_action' => $installed->last_pending_action,
				'developer' => $addOn->dev ?: null,
				'path' => $addOn->getAddOnDirectory(),
			];
		}

		$json = json_encode([
			'addons' => $jsonAddOns,
			'summary' => [
				'total' => $total,
				'active' => $totalActive,
				'inactive' => $totalInactive,
			],
		], JSON_PRETTY_PRINT);

		if ($json === false)
		{
			$output->writeln('<error>Failed to encode add-on list as JSON: ' . json_last_error_msg() . '</error>');
			return 1;
		}

		$output->writeln($json);

		return 0;
	}

	/**
	 * @return string[]
	 */
	protected function getTableHeaders(bool $isVerbose): array
	{
		if ($isVerbose)
		{
			return ['Add-on ID', 'Title', 'Version', 'Version ID', 'Active', 'Legacy', 'Processing', 'Pending Action', 'Developer', 'Path'];
		}

		return ['Add-on ID', 'Title', 'Version', 'Active'];
	}

	/**
	 * @param AddOn[] $addOns
	 *
	 * @return array[]
	 */
	protected function buildTableRows(array $addOns, bool $isVerbose): array
	{
		$rows = [];

		foreach ($addOns AS $addOn)
		{
			if ($isVerbose)
			{
				$installed = $addOn->getInstalledAddOn();

				$rows[] = [
					$addOn->addon_id,
					$addOn->title,
					$addOn->version_string,
					$addOn->version_id,
					$addOn->isActive() ? 'Yes' : 'No',
					$addOn->isLegacy() ? 'Yes' : 'No',
					$installed->is_processing ? 'Yes' : 'No',
					$installed->last_pending_action ?: '-',
					$addOn->dev ?: '-',
					$addOn->getAddOnDirectory(),
				];
			}
			else
			{
				$rows[] = [
					$addOn->addon_id,
					$addOn->title,
					$addOn->version_string,
					$addOn->isActive() ? 'Yes' : 'No',
				];
			}
		}

		return $rows;
	}
}
