<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Util\File;

use function in_array;

class StubPublishMakeCommand extends AbstractMakeCommand
{
	protected function configure(): void
	{
		$this
			->setName('xf-make:stub-publish')
			->setDescription('Publish stub templates to an add-on for customization')
			->addArgument(
				'stub',
				InputArgument::OPTIONAL,
				'Specific stub to publish (e.g. "entity", "controller.pub")'
			)
			->addOption(
				'addon',
				'a',
				InputOption::VALUE_REQUIRED,
				'Target add-on ID'
			)
			->addOption(
				'all',
				null,
				InputOption::VALUE_NONE,
				'Publish all stubs'
			)
			->addOption(
				'force',
				'f',
				InputOption::VALUE_NONE,
				'Overwrite existing stubs'
			)
			->addOption(
				'list',
				'l',
				InputOption::VALUE_NONE,
				'List available stubs and their placeholders'
			);
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		// If just listing, no interaction needed
		if ($input->getOption('list'))
		{
			return;
		}

		$io = new SymfonyStyle($input, $output);

		// Prompt for addon if not provided
		if (!$input->getOption('addon'))
		{
			$addOnId = $this->promptForAddOn($io, false);
			$input->setOption('addon', $addOnId);
		}

		// If no stub specified and not --all, show interactive selection
		if (!$input->getArgument('stub') && !$input->getOption('all'))
		{
			$stubs = $this->getAvailableStubs();
			$choices = [];
			foreach ($stubs AS $stub)
			{
				$name = $this->getStubName($stub);
				$choices[$name] = $name;
			}
			$choices['--all--'] = 'All stubs';

			$selected = $io->choice(
				'Which stub would you like to publish?',
				$choices
			);

			if ($selected === '--all--')
			{
				$input->setOption('all', true);
			}
			else
			{
				$input->setArgument('stub', $selected);
			}
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		// Handle --list option
		if ($input->getOption('list'))
		{
			return $this->listStubs($io);
		}

		$addOnId = $input->getOption('addon');
		if (!$addOnId)
		{
			$io->error('The --addon option is required.');
			return Command::FAILURE;
		}

		if (!$this->validateAddOn($addOnId, $io, false))
		{
			return Command::FAILURE;
		}

		$stubsDir = $this->getAddOnDirectory() . '/_stubs';
		$availableStubs = $this->getAvailableStubs();
		$stubsByName = [];
		foreach ($availableStubs AS $stubPath)
		{
			$stubsByName[$this->getStubName($stubPath)] = $stubPath;
		}

		// Determine which stubs to publish
		$stubsToPublish = [];
		if ($input->getOption('all'))
		{
			$stubsToPublish = $stubsByName;
		}
		else
		{
			$stubName = $input->getArgument('stub');
			if (!$stubName)
			{
				$io->error('You must specify a stub name or use --all.');
				return Command::FAILURE;
			}
			if (!isset($stubsByName[$stubName]))
			{
				$io->error("Unknown stub: {$stubName}");
				$io->note('Use --list to see available stubs.');
				return Command::FAILURE;
			}
			$stubsToPublish = [$stubName => $stubsByName[$stubName]];
		}

		// Publish each stub
		$published = 0;
		$skipped = 0;
		$errors = 0;
		$force = $input->getOption('force');

		foreach ($stubsToPublish AS $sourcePath)
		{
			$filename = basename($sourcePath);
			$targetPath = $stubsDir . '/' . $filename;

			if (file_exists($targetPath) && !$force)
			{
				$io->note("Skipped {$filename} (already exists, use --force to overwrite)");
				$skipped++;
				continue;
			}

			$content = file_get_contents($sourcePath);
			if ($content === false)
			{
				$io->error("Failed to read stub: {$filename}");
				$errors++;
				continue;
			}

			$this->ensureDirectory($targetPath);
			if (!File::writeFile($targetPath, $content, false))
			{
				$io->error("Failed to write stub: {$filename}");
				$errors++;
				continue;
			}

			$io->text("Published: {$filename}");

			$published++;
		}
		$io->newLine();
		if ($published > 0)
		{
			$io->success("Published {$published} stub(s) to {$stubsDir}");
		}
		if ($skipped > 0 && $published === 0 && $errors === 0)
		{
			$io->warning("All stubs already exist. Use --force to overwrite.");
		}
		if ($errors > 0)
		{
			$io->warning("{$errors} stub(s) failed to publish.");
			return Command::FAILURE;
		}

		return Command::SUCCESS;
	}

	protected function listStubs(SymfonyStyle $io): int
	{
		$stubs = $this->getAvailableStubs();

		$io->title('Available Stubs');

		$rows = [];
		foreach ($stubs AS $stubPath)
		{
			$name = $this->getStubName($stubPath);
			$placeholders = $this->extractPlaceholders($stubPath);
			$rows[] = [
				$name,
				$placeholders ? implode(', ', $placeholders) : '(none)',
			];
		}

		$io->table(['Stub', 'Placeholders'], $rows);

		$io->section('Usage');
		$io->text([
			'Publish a specific stub:',
			'  php cmd.php xf-make:stub-publish entity --addon=Vendor/AddOn',
			'',
			'Publish all stubs:',
			'  php cmd.php xf-make:stub-publish --addon=Vendor/AddOn --all',
			'',
			'Published stubs will be placed in: {AddOnDir}/_stubs/',
			'The make commands will automatically use custom stubs when present.',
		]);

		return Command::SUCCESS;
	}

	/**
	 * Get all available stub files from the core stubs directory
	 *
	 * @return string[] Array of full paths to stub files
	 */
	protected function getAvailableStubs(): array
	{
		$stubsDir = __DIR__ . '/stubs';
		$stubs = glob($stubsDir . '/*.stub') ?: [];

		$stubs = array_filter($stubs, function ($path)
		{
			return strpos(basename($path), '.method.') === false;
		});

		sort($stubs);
		return $stubs;
	}

	/**
	 * Get the stub name from its path (filename without .stub extension)
	 */
	protected function getStubName(string $stubPath): string
	{
		return basename($stubPath, '.stub');
	}

	/**
	 * Extract placeholder names from a stub file
	 *
	 * Returns placeholders sorted with 'namespace' first, 'class' second,
	 * then remaining placeholders in alphabetical order.
	 *
	 * @return string[] Array of placeholder names (without {{ }})
	 */
	protected function extractPlaceholders(string $stubPath): array
	{
		$content = file_get_contents($stubPath);
		if ($content === false)
		{
			return [];
		}

		preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $content, $matches);

		$placeholders = array_unique($matches[1]);

		return $this->sortPlaceholders($placeholders);
	}

	/**
	 * Sort placeholders: namespace first, class second, then alphabetical
	 *
	 * @param string[] $placeholders
	 * @return string[]
	 */
	protected function sortPlaceholders(array $placeholders): array
	{
		$priority = ['namespace', 'class'];
		$prioritized = [];
		$remaining = [];

		foreach ($placeholders AS $placeholder)
		{
			if (in_array($placeholder, $priority, true))
			{
				$prioritized[$placeholder] = $placeholder;
			}
			else
			{
				$remaining[] = $placeholder;
			}
		}

		sort($remaining);

		$result = [];
		foreach ($priority AS $key)
		{
			if (isset($prioritized[$key]))
			{
				$result[] = $key;
			}
		}

		return array_merge($result, $remaining);
	}
}
