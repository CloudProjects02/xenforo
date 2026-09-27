<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function in_array;

abstract class AbstractClassMakeCommand extends AbstractMakeCommand
{
	/**
	 * @var string
	 */
	protected $type;

	/**
	 * @var InputInterface
	 */
	protected $input;

	/**
	 * @var string[]
	 */
	protected $reservedNames = [
		'abstract', 'and', 'array', 'as', 'break', 'callable', 'case',
		'catch', 'class', 'clone', 'const', 'continue', 'declare', 'default',
		'die', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor',
		'endforeach', 'endif', 'endswitch', 'endwhile', 'enum', 'eval', 'exit',
		'extends', 'false', 'final', 'finally', 'fn', 'for', 'foreach',
		'function', 'global', 'goto', 'if', 'implements', 'include',
		'include_once', 'instanceof', 'insteadof', 'interface', 'isset',
		'list', 'match', 'namespace', 'new', 'never', 'null', 'or', 'print',
		'private', 'protected', 'public', 'readonly', 'require', 'require_once',
		'return', 'self', 'static', 'switch', 'throw', 'trait', 'true', 'try',
		'unset', 'use', 'var', 'while', 'xor', 'yield',
	];

	/**
	 * Get the stub filename for this command.
	 */
	abstract protected function getStub(): string;

	/**
	 * Get the default sub-namespace for this type.
	 */
	protected function getDefaultSubNamespace(): string
	{
		return $this->type;
	}

	/**
	 * Build the class content from the stub.
	 */
	protected function buildClass(string $name): string
	{
		$stubPath = $this->resolveStubPath($this->getStub());
		$stub = file_get_contents($stubPath);

		if ($stub === false)
		{
			throw new \RuntimeException("Failed to load stub file: $stubPath");
		}

		$this->replaceNamespace($stub, $name);
		$this->replaceClass($stub, $name);

		return $stub;
	}

	/**
	 * Hook called after successful file generation.
	 */
	protected function afterGenerate(InputInterface $input, OutputInterface $output, SymfonyStyle $io): void
	{
	}

	protected function configure(): void
	{
		parent::configure();

		$typeLower = strtolower($this->type);

		$this
			->addArgument(
				'name',
				InputArgument::REQUIRED,
				"The name of the {$typeLower} (e.g. \"Demo/Thing:{$this->type}Name\" or \"{$this->type}Name\" with --addon)"
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->input = $input;

		$io = new SymfonyStyle($input, $output);
		$this->resolveAddOnFromArgument($input, 'name');

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

		$name = trim($input->getArgument('name'));
		if (!$this->validateClassPath($name, $io, $this->type . ' name'))
		{
			return Command::FAILURE;
		}

		$nameParts = preg_split('/[\\/\\\\]/', $name);
		foreach ($nameParts AS $part)
		{
			if ($this->isReservedName($part))
			{
				$io->error("The name '$part' is a reserved PHP keyword.");
				return Command::FAILURE;
			}
		}

		$qualifiedName = $this->qualifyClass($name);

		$path = $this->getPath($qualifiedName);
		if (file_exists($path) && !$input->getOption('force'))
		{
			$io->error("$this->type already exists at $path");
			$io->note('Use --force to overwrite.');
			return Command::FAILURE;
		}

		$content = $this->buildClass($qualifiedName);
		$this->writeFile($path, $content);

		$io->success("$this->type [$path] created successfully.");

		$this->afterGenerate($input, $output, $io);

		return Command::SUCCESS;
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);

		$this->interactAddOn($input, $io, 'name');

		if (!$input->getArgument('name'))
		{
			$name = $io->ask(
				'What should the ' . strtolower($this->type) . ' be named?',
				null,
				function ($value)
				{
					if (empty($value))
					{
						throw new \InvalidArgumentException('Name cannot be empty.');
					}
					return $value;
				}
			);
			$input->setArgument('name', $name);
		}
	}

	protected function qualifyClass(string $name): string
	{
		$name = ltrim($name, '\\/');
		$name = str_replace('/', '\\', $name);

		$addOnClass = str_replace('/', '\\', $this->addOnId);
		$subNamespace = $this->getDefaultSubNamespace();

		return $addOnClass . '\\' . $subNamespace . '\\' . $name;
	}

	protected function getPath(string $qualifiedName): string
	{
		$path = str_replace('\\', '/', $qualifiedName) . '.php';

		if ($this->addOnId === 'XF')
		{
			return \XF::getSourceDirectory() . '/' . $path;
		}

		return \XF::getAddOnDirectory() . '/' . $path;
	}

	protected function replaceNamespace(string &$stub, string $qualifiedName): void
	{
		$namespace = $this->getNamespace($qualifiedName);

		$stub = str_replace(
			['{{ namespace }}', '{{namespace}}'],
			$namespace,
			$stub
		);
	}

	protected function replaceClass(string &$stub, string $qualifiedName): void
	{
		$class = $this->getClassName($qualifiedName);

		$stub = str_replace(
			['{{ class }}', '{{class}}'],
			$class,
			$stub
		);
	}

	protected function getNamespace(string $qualifiedName): string
	{
		$parts = explode('\\', $qualifiedName);
		array_pop($parts);
		return implode('\\', $parts);
	}

	protected function getClassName(string $qualifiedName): string
	{
		$parts = explode('\\', $qualifiedName);
		return array_pop($parts);
	}

	protected function isReservedName(string $name): bool
	{
		return in_array(strtolower($name), $this->reservedNames);
	}

	protected function runCommand(string $name, array $arguments, OutputInterface $output): bool
	{
		$arguments['--force'] = $this->input->getOption('force');
		$io = new SymfonyStyle($this->input, $output);
		return $this->runMakeCommand($name, $arguments, $output, $io);
	}

	protected function toSnakeCase(string $value): string
	{
		return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
	}
}
