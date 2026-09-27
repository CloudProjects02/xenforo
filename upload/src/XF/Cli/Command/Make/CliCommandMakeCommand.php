<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function strlen;

class CliCommandMakeCommand extends AbstractMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:cli-command')
			->setDescription('Create a CLI command class')
			->setAliases(['xf-make:command'])
			->addArgument(
				'name',
				InputArgument::REQUIRED,
				'The command class name (e.g. "MyCommand" or "Import/Users")'
			)
			->addOption(
				'command-name',
				'c',
				InputOption::VALUE_REQUIRED,
				'CLI command name (e.g. "demo:my-task"). Auto-generated if not specified.'
			)
			->addOption(
				'description',
				'd',
				InputOption::VALUE_REQUIRED,
				'Command description',
				''
			);
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);

		$this->interactAddOn($input, $io, 'name');

		if (!$input->getArgument('name'))
		{
			$name = $io->ask(
				'Enter the command class name (e.g. MyCommand or Import/Users)',
				null,
				function ($value)
				{
					if (empty($value))
					{
						throw new \InvalidArgumentException('Class name cannot be empty.');
					}
					return $value;
				}
			);
			$input->setArgument('name', $name);
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		$addOnId = $input->getOption('addon');
		if (!$addOnId)
		{
			$io->error('The --addon option is required.');
			return Command::FAILURE;
		}

		if (!$this->validateAddOn($addOnId, $io))
		{
			return Command::FAILURE;
		}

		$name = $input->getArgument('name');
		$name = $this->normalizeClassName($name);

		if (!$this->validateClassName($name, $io))
		{
			return Command::FAILURE;
		}

		$filePath = $this->getCommandFilePath($name);
		$force = $input->getOption('force');

		if (file_exists($filePath) && !$force)
		{
			$io->error("Command class already exists at $filePath");
			$io->note('Use --force to overwrite.');
			return Command::FAILURE;
		}

		$commandName = $input->getOption('command-name') ?: $this->generateCommandName($name);
		$description = $input->getOption('description');

		$namespace = $this->buildNamespace($name);
		$className = $this->getShortClassName($name);

		$stub = file_get_contents($this->resolveStubPath('cli-command.stub'));
		$stub = str_replace(
			[
				'{{ namespace }}',
				'{{ class }}',
				'{{ commandName }}',
				'{{ description }}',
			],
			[
				$namespace,
				$className,
				$commandName,
				$description,
			],
			$stub
		);

		$this->writeFile($filePath, $stub);

		$io->success('CLI command class created successfully.');

		$io->table(
			['Property', 'Value'],
			[
				['Class', $namespace . '\\' . $className],
				['File', $filePath],
				['Command', $commandName],
			]
		);

		return Command::SUCCESS;
	}

	protected function normalizeClassName(string $name): string
	{
		$name = str_replace('\\', '/', $name);

		$name = preg_replace('/\.php$/i', '', $name);

		return $name;
	}

	protected function validateClassName(string $name, SymfonyStyle $io): bool
	{
		$parts = explode('/', $name);

		foreach ($parts AS $part)
		{
			if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $part))
			{
				$io->error("Invalid class name part: '$part'. Each part must be PascalCase (start with uppercase letter).");
				return false;
			}
		}

		return true;
	}

	protected function getCommandFilePath(string $name): string
	{
		$relativePath = 'Cli/Command/' . $name . '.php';
		return $this->getAddOnDirectory() . '/' . $relativePath;
	}

	protected function buildNamespace(string $name): string
	{
		$baseNamespace = str_replace('/', '\\', $this->addOnId) . '\\Cli\\Command';

		if (strpos($name, '/') !== false)
		{
			$parts = explode('/', $name);
			array_pop($parts);
			if (!empty($parts))
			{
				$baseNamespace .= '\\' . implode('\\', $parts);
			}
		}

		return $baseNamespace;
	}

	protected function getShortClassName(string $name): string
	{
		if (strpos($name, '/') !== false)
		{
			$parts = explode('/', $name);
			return array_pop($parts);
		}

		return $name;
	}

	protected function generateCommandName(string $className): string
	{
		$prefix = strtolower(str_replace(['/', '\\'], '-', $this->addOnId));

		$suffix = $this->camelCaseToKebabCase($className);
		$suffix = str_replace('/', '-', $suffix);

		$stripped = preg_replace('/-command$/i', '', $suffix);
		if (strlen($stripped) > 3 && $stripped !== $prefix)
		{
			$suffix = $stripped;
		}

		return $prefix . ':' . $suffix;
	}

	protected function camelCaseToKebabCase(string $value): string
	{
		$parts = explode('/', $value);
		$converted = [];

		foreach ($parts AS $part)
		{
			$kebab = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $part));
			$converted[] = $kebab;
		}

		return implode('/', $converted);
	}
}
