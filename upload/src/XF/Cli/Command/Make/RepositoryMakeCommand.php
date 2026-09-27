<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RepositoryMakeCommand extends AbstractClassMakeCommand
{
	/**
	 * @var string
	 */
	protected $type = 'Repository';

	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:repository')
			->setDescription('Create a new repository class');
	}

	protected function getStub(): string
	{
		return 'repository.stub';
	}

	protected function afterGenerate(InputInterface $input, OutputInterface $output, SymfonyStyle $io): void
	{
		$name = $input->getArgument('name');
		$qualifiedName = $this->qualifyClass($name);

		$io->table(
			['Property', 'Value'],
			[
				['Class', $qualifiedName],
				['File', $this->getPath($qualifiedName)],
			]
		);
	}
}
