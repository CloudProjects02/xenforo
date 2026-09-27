<?php

declare(strict_types=1);

namespace XF\Cli\Command\Development;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Cli\Command\AbstractCommand;
use XF\Util\File;

use function is_file, str_replace, strlen, substr;

class InstallAiAgents extends AbstractCommand
{
	use RequiresDevModeTrait;

	protected const STUB_DIR = __DIR__ . '/stubs/ai-agents';

	protected const DEFAULT_CONFIG = [
		'rootFile' => 'AGENTS.md',
		'skillsPath' => '.agents/skills',
	];

	protected const CLAUDE_CONFIG = [
		'rootFile' => 'CLAUDE.md',
		'skillsPath' => '.claude/skills',
	];

	protected function configure(): void
	{
		$this
			->setName('xf-dev:install-ai-agents')
			->setDescription('Install AI agent convention files')
			->addOption(
				'force',
				'f',
				InputOption::VALUE_NONE,
				'Overwrite files if they already exist'
			)
			->addOption(
				'with-claude',
				null,
				InputOption::VALUE_NONE,
				'Install default files plus Claude-compatible files'
			)
			->addOption(
				'only-claude',
				null,
				InputOption::VALUE_NONE,
				'Install only Claude-compatible files'
			)
			->addOption(
				'dry-run',
				null,
				InputOption::VALUE_NONE,
				'Show which files would be written without making changes'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		$withClaude = (bool) $input->getOption('with-claude');
		$onlyClaude = (bool) $input->getOption('only-claude');

		if ($withClaude && $onlyClaude)
		{
			$io->error('Options --with-claude and --only-claude cannot be used together.');
			return Command::FAILURE;
		}

		$configs = $this->getTargetConfigs($withClaude, $onlyClaude);
		$plan = $this->buildInstallPlan($configs);

		if (!$plan)
		{
			$io->error('No files selected for installation.');
			return Command::FAILURE;
		}

		$rootDir = \XF::getRootDirectory();
		$force = (bool) $input->getOption('force');
		$dryRun = (bool) $input->getOption('dry-run');

		$io->title('AI Agent Convention Installer');
		$io->text("Install mode: " . $this->getModeLabel($withClaude, $onlyClaude));
		$io->text("Mode: " . ($dryRun ? 'dry-run' : 'write'));
		$io->newLine();

		$rows = [];
		foreach ($plan AS $item)
		{
			$rows[] = [$item['destination'], $item['stub']];
		}
		$io->table(['Destination', 'Source stub'], $rows);

		$written = 0;
		$skipped = 0;
		$errors = 0;

		foreach ($plan AS $item)
		{
			$stubPath = $item['stubPath'];
			$destinationPath = $rootDir . \XF::$DS . $item['destination'];

			if (!is_file($stubPath))
			{
				$io->error("Stub not found: {$item['stub']}");
				$errors++;
				continue;
			}

			if (is_file($destinationPath) && !$force)
			{
				$io->note("Skipped {$item['destination']} (already exists, use --force to overwrite)");
				$skipped++;
				continue;
			}

			if ($dryRun)
			{
				$io->text("Would write: {$item['destination']}");
				continue;
			}

			$content = file_get_contents($stubPath);
			if ($content === false)
			{
				$io->error("Failed to read stub: {$item['stub']}");
				$errors++;
				continue;
			}

			if (!empty($item['replacements']))
			{
				$content = str_replace(
					array_keys($item['replacements']),
					array_values($item['replacements']),
					$content
				);
			}

			if (!File::writeFile($destinationPath, $content, false))
			{
				$io->error("Failed to write: {$item['destination']}");
				$errors++;
				continue;
			}

			$io->text("Wrote: {$item['destination']}");
			$written++;
		}

		$io->newLine();

		if ($dryRun)
		{
			$io->success('Dry-run completed.');
			return Command::SUCCESS;
		}

		if ($errors > 0)
		{
			$io->warning("$errors file(s) failed.");
			return Command::FAILURE;
		}

		if ($written > 0)
		{
			$io->success("Installed $written file(s).");
		}
		else
		{
			$io->note('No files were written.');
		}

		if ($skipped > 0)
		{
			$io->note("Skipped $skipped existing file(s).");
		}

		return Command::SUCCESS;
	}

	/**
	 * @return list<array{rootFile: string, skillsPath: string}>
	 */
	protected function getTargetConfigs(bool $withClaude, bool $onlyClaude): array
	{
		if ($onlyClaude)
		{
			return [static::CLAUDE_CONFIG];
		}

		$configs = [static::DEFAULT_CONFIG];
		if ($withClaude)
		{
			$configs[] = static::CLAUDE_CONFIG;
		}

		return $configs;
	}

	protected function getModeLabel(bool $withClaude, bool $onlyClaude): string
	{
		if ($onlyClaude)
		{
			return 'claude-only';
		}

		if ($withClaude)
		{
			return 'default+claude';
		}

		return 'default';
	}

	/**
	 * @param list<array{rootFile: string, skillsPath: string}> $configs
	 * @return list<array{destination: string, stub: string, stubPath: string, replacements: array<string, string>}>
	 */
	protected function buildInstallPlan(array $configs): array
	{
		$plan = [];

		foreach ($configs AS $config)
		{
			$rootFile = $config['rootFile'];
			$skillsPath = $config['skillsPath'];

			$plan[] = [
				'destination' => $rootFile,
				'stub' => 'ROOT.md.stub',
				'stubPath' => static::STUB_DIR . '/ROOT.md.stub',
				'replacements' => [
					'{{ROOT_FILE_NAME}}' => $rootFile,
					'{{SKILLS_PATH}}' => $skillsPath,
				],
			];

			$skillStubs = $this->discoverSkillStubs();
			foreach ($skillStubs AS $relativePath => $absolutePath)
			{
				$destinationFile = str_replace('.stub', '', $relativePath);
				$plan[] = [
					'destination' => $skillsPath . '/' . $destinationFile,
					'stub' => 'skills/' . $relativePath,
					'stubPath' => $absolutePath,
					'replacements' => [],
				];
			}
		}

		return $plan;
	}

	/**
	 * @return array<string, string>
	 */
	protected function discoverSkillStubs(): array
	{
		$skillsDir = static::STUB_DIR . '/skills';
		$stubs = [];

		if (!is_dir($skillsDir))
		{
			return $stubs;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($skillsDir, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		/** @var \SplFileInfo $file */
		foreach ($iterator AS $file)
		{
			if (substr($file->getFilename(), -5) !== '.stub')
			{
				continue;
			}

			$relativePath = substr($file->getPathname(), strlen($skillsDir) + 1);
			$stubs[$relativePath] = $file->getPathname();
		}

		ksort($stubs);

		return $stubs;
	}
}
