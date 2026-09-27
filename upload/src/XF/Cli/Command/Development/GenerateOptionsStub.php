<?php

namespace XF\Cli\Command\Development;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Cli\Command\AbstractCommand;
use XF\Finder\OptionFinder;
use XF\Repository\OptionRepository;

class GenerateOptionsStub extends AbstractCommand
{
	use RequiresDevModeTrait;

	protected function configure(): void
	{
		$this
			->setName('xf-dev:generate-options-stub')
			->setDescription('Generates an option stub file for static analysis')
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'Path to save the generated stub file',
				'Options.stub'
			)
			->addOption(
				'print',
				null,
				InputOption::VALUE_NONE,
				'If enabled, the stub is printed instead of written to a file'
			)
			->addOption(
				'addon',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Limit the stub to options owned by the given add-on(s). Defaults to core only.',
				['XF']
			);
	}

	protected function execute(
		InputInterface $input,
		OutputInterface $output
	): int
	{
		$io = new SymfonyStyle($input, $output);

		$stub = $this->getOptionStub($input->getOption('addon'));
		if ($stub === null)
		{
			$io->error('The stubs could not be generated.');

			return static::FAILURE;
		}

		if ($input->getOption('print'))
		{
			$io->text($stub);

			return static::SUCCESS;
		}

		$path = $input->getArgument('path');
		$io->text("Writing {$path}...");

		if (!is_writable(dirname($path)))
		{
			$io->error('The file could not be written.');

			return static::FAILURE;
		}

		file_put_contents($path, $stub);
		$io->success('The file was written successfully.');

		return static::SUCCESS;
	}

	protected function getOptionStub(array $addOnIds): ?string
	{
		$optionRepo = \XF::repository(OptionRepository::class);
		$optionFinder = $this->getOptionFinder($addOnIds);

		return $optionRepo->getOptionCacheFileValue($optionFinder);
	}

	protected function getOptionFinder(array $addOnIds): OptionFinder
	{
		$finder = \XF::finder(OptionFinder::class)
			->order('option_id');

		if ($addOnIds)
		{
			$finder->where('addon_id', $addOnIds);
		}

		return $finder;
	}
}
