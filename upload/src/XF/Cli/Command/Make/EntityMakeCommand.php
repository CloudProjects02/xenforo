<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Util\Str;

class EntityMakeCommand extends AbstractClassMakeCommand
{
	/**
	 * @var string
	 */
	protected $type = 'Entity';

	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:entity')
			->setDescription('Create a new entity class')
			->addOption('finder', null, InputOption::VALUE_NONE, 'Also create a Finder class')
			->addOption('repository', null, InputOption::VALUE_NONE, 'Also create a Repository class')
			->addOption('all', null, InputOption::VALUE_NONE, 'Create Entity, Finder, and Repository')
			->addOption('table', 't', InputOption::VALUE_REQUIRED, 'The database table name');
	}

	protected function getStub(): string
	{
		return 'entity.stub';
	}

	protected function getTableName(string $className): string
	{
		$table = trim((string) $this->input->getOption('table'));

		return $table !== '' ? $table : 'xf_' . Str::toSnakeCase($className);
	}

	protected function buildClass(string $name): string
	{
		$stub = parent::buildClass($name);

		$className = $this->getClassName($name);
		$tableName = $this->getTableName($className);

		return str_replace(
			['{{ table }}', '{{ shortName }}'],
			[$tableName, $this->addOnId . ':' . $className],
			$stub
		);
	}

	protected function afterGenerate(InputInterface $input, OutputInterface $output, SymfonyStyle $io): void
	{
		$name = $input->getArgument('name');
		$qualifiedName = $this->qualifyClass($name);
		$className = $this->getClassName($qualifiedName);
		$tableName = $this->getTableName($className);
		$shortName = $this->addOnId . ':' . $className;

		$io->table(
			['Property', 'Value'],
			[
				['Class', $qualifiedName],
				['File', $this->getPath($qualifiedName)],
				['Table', $tableName],
				['Short Name', $shortName],
			]
		);

		if ($input->getOption('all'))
		{
			$input->setOption('finder', true);
			$input->setOption('repository', true);
		}

		$force = $input->getOption('force');

		if ($input->getOption('finder'))
		{
			$this->runCommand('xf-make:finder', [
				'name' => $name . 'Finder',
				'--addon' => $this->addOnId,
				'--entity' => $name,
				'--force' => $force,
			], $output);
		}

		if ($input->getOption('repository'))
		{
			$this->runCommand('xf-make:repository', [
				'name' => $name . 'Repository',
				'--addon' => $this->addOnId,
				'--force' => $force,
			], $output);
		}
	}
}
