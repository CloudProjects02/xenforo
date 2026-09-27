<?php

namespace XF\Cli\Command\Development;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AbstractCommand;
use XF\DevelopmentOutput\Template;
use XF\Repository\AddOnRepository;
use XF\Template\Compiler\Exception;

use function count;

class LintTemplates extends AbstractCommand
{
	use RequiresDevModeTrait;

	protected function configure()
	{
		$this
			->setName('xf-dev:lint-templates')
			->setDescription('Validates that all template files compile without errors')
			->addOption(
				'addon',
				null,
				InputOption::VALUE_REQUIRED,
				'Add-on ID to process'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$onlyAddOn = $input->getOption('addon');
		$start = microtime(true);

		$devOutput = \XF::app()->developmentOutput();
		$addOns = $devOutput->getAvailableTypeFilesByAddOn('templates');
		$installedAddOns = \XF::repository(AddOnRepository::class)->getInstalledAddOnData();

		$compiler = \XF::app()->templateCompiler();

		$templateOutputHandler = $devOutput->getHandler(Template::class);

		$errors = [];
		$totalFiles = 0;
		$verbose = ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE);

		foreach ($addOns AS $addOnId => $files)
		{
			if ($onlyAddOn && $onlyAddOn !== $addOnId)
			{
				continue;
			}

			if (!isset($installedAddOns[$addOnId]))
			{
				$output->writeln("Skipping $addOnId - not installed.");
				continue;
			}

			$output->writeln("Linting $addOnId templates...");

			foreach ($files AS $fileName => $path)
			{
				$totalFiles++;

				$templateName = $templateOutputHandler->convertTemplateFileToName($fileName);

				if ($verbose)
				{
					$output->writeln("Checking $templateName...");
				}

				$content = file_get_contents($path);

				try
				{
					$compiler->compile($content);
				}
				catch (Exception $e)
				{
					$errors[] = [
						'addOn' => $addOnId,
						'template' => $templateName,
						'error' => $e->getMessage(),
					];
				}
			}

			$output->writeln("Linted $addOnId.");
		}

		$output->writeln("");

		if ($errors)
		{
			$output->writeln("Errors found:");

			foreach ($errors AS $error)
			{
				$output->writeln(sprintf(
					'[%s] %s: %s',
					$error['addOn'],
					$error['template'],
					$error['error']
				));
			}

			$output->writeln("");
			$output->writeln(sprintf(
				'%d error(s) found out of %d template(s) linted. (%.02fs)',
				count($errors),
				$totalFiles,
				microtime(true) - $start
			));

			return 1;
		}

		$output->writeln(sprintf(
			'Done. %d template(s) linted successfully. (%.02fs)',
			$totalFiles,
			microtime(true) - $start
		));

		return 0;
	}
}
