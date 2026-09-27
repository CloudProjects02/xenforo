<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FinderMakeCommand extends AbstractClassMakeCommand
{
	/**
	 * @var string
	 */
	protected $type = 'Finder';

	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:finder')
			->setDescription('Create a new finder class')
			->addOption('entity', null, InputOption::VALUE_REQUIRED, 'The entity class name (short name, e.g., "Thing")');
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		parent::interact($input, $output);

		$io = new SymfonyStyle($input, $output);

		if (!$input->getOption('entity'))
		{
			$entityName = $io->ask(
				'Which entity is this finder for? (e.g. Article)',
				null,
				function ($value)
				{
					if (empty($value))
					{
						throw new \InvalidArgumentException('Entity name cannot be empty.');
					}
					return $value;
				}
			);
			$input->setOption('entity', $entityName);
		}
	}

	protected function getStub(): string
	{
		return 'finder.stub';
	}

	protected function buildClass(string $name): string
	{
		$stub = parent::buildClass($name);

		$entityName = $this->input->getOption('entity');
		$entityClass = str_replace('/', '\\', $this->addOnId) . '\\Entity\\' . $entityName;

		return str_replace(
			['{{ entity }}', '{{ entityShort }}'],
			[$entityClass, $entityName],
			$stub
		);
	}

	protected function afterGenerate(InputInterface $input, OutputInterface $output, SymfonyStyle $io): void
	{
		$name = $input->getArgument('name');
		$qualifiedName = $this->qualifyClass($name);
		$entityName = $input->getOption('entity');

		$io->table(
			['Property', 'Value'],
			[
				['Class', $qualifiedName],
				['File', $this->getPath($qualifiedName)],
				['Entity', $entityName],
			]
		);
	}
}
