<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\ClassExtension;
use XF\Finder\ClassExtensionFinder;

class ExtensionMakeCommand extends AbstractClassMakeCommand
{
	/**
	 * @var string
	 */
	protected $type = 'ClassExtension';

	/**
	 * @var string
	 */
	protected $baseClass;

	protected function configure(): void
	{
		// Note: We intentionally don't call parent::configure() because AbstractClassMakeCommand
		// adds a 'name' argument that we don't use - we use 'base-class' instead.
		// We manually add the common options from AbstractMakeCommand here.

		$this
			->setName('xf-make:extension')
			->setDescription('Create a new class extension')
			->addArgument(
				'base-class',
				InputArgument::REQUIRED,
				'The class to extend (e.g. "Demo/Thing:XF/Entity/Thread" or "XF/Entity/Thread" with --addon)'
			)
			->addOption(
				'addon',
				'a',
				InputOption::VALUE_REQUIRED,
				'Add-on ID (not required if using AddOn:Name format)'
			)
			->addOption(
				'force',
				'f',
				InputOption::VALUE_NONE,
				'Force operation even if target exists'
			)
			->addOption(
				'execute-order',
				'o',
				InputOption::VALUE_REQUIRED,
				'Execution priority order',
				'10'
			)
			->addOption(
				'inactive',
				null,
				InputOption::VALUE_NONE,
				'Create the extension as inactive'
			);
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);

		$this->parseBaseClassColonSyntax($input);

		if (!$input->getArgument('base-class'))
		{
			$baseClass = $io->ask(
				'What class do you want to extend?',
				null,
				function ($value)
				{
					if (empty($value))
					{
						throw new \InvalidArgumentException('Base class cannot be empty.');
					}
					return $value;
				}
			);
			$input->setArgument('base-class', $baseClass);
		}

		if (!$input->getOption('addon'))
		{
			$addOnId = $this->promptForAddOn($io, false);
			$input->setOption('addon', $addOnId);
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->input = $input;

		$io = new SymfonyStyle($input, $output);

		// Parse colon syntax in case interact() was skipped (non-interactive mode)
		$this->parseBaseClassColonSyntax($input);

		$this->addOnId = $input->getOption('addon');
		if (!$this->addOnId)
		{
			$io->error('The --addon option is required.');
			return Command::FAILURE;
		}

		if ($this->addOnId === 'XF')
		{
			$io->error('Class extensions cannot be created for the XF core. Extensions are meant for add-ons to extend core or other add-on classes.');
			return Command::FAILURE;
		}

		$this->addOnObj = \XF::app()->addOnManager()->getById($this->addOnId);
		if ($this->addOnObj === null || !$this->addOnObj->isInstalled())
		{
			$io->error("Add-on '$this->addOnId' is not installed.");
			return Command::FAILURE;
		}

		$this->baseClass = $this->normalizeClassName($input->getArgument('base-class'));

		if (!\XF::$autoLoader->findFile($this->baseClass))
		{
			$io->error("The base class '$this->baseClass' does not exist or cannot be loaded.");
			return Command::FAILURE;
		}

		$qualifiedName = $this->qualifyClass($this->baseClass);
		$existing = \XF::finder(ClassExtensionFinder::class)->where([
			'from_class' => $this->baseClass,
			'to_class' => $qualifiedName,
		])->fetchOne();
		if ($existing && !$this->validateEntityOwner($existing, $io))
		{
			return Command::FAILURE;
		}

		$path = $this->getPath($qualifiedName);
		if (file_exists($path) && !$input->getOption('force'))
		{
			$io->error("Extension class already exists at $path");
			$io->note('Use --force to overwrite.');
			return Command::FAILURE;
		}

		$content = $this->buildClass($qualifiedName);
		$this->writeFile($path, $content);

		$io->success("Class extension created successfully.");

		$io->table(
			['Property', 'Value'],
			[
				['Extension Class', $qualifiedName],
				['Base Class', $this->baseClass],
				['File', $path],
			]
		);

		$this->afterGenerate($input, $output, $io);

		return Command::SUCCESS;
	}

	protected function afterGenerate(InputInterface $input, OutputInterface $output, SymfonyStyle $io): void
	{
		$extensionClass = $this->qualifyClass($this->baseClass);

		$existing = \XF::finder(ClassExtensionFinder::class)->where([
			'from_class' => $this->baseClass,
			'to_class' => $extensionClass,
		])->fetchOne();

		if ($existing)
		{
			if (!$input->getOption('force'))
			{
				$io->note('Class extension entry already exists in database.');
				return;
			}
			$extension = $existing;
		}
		else
		{
			$extension = \XF::em()->create(ClassExtension::class);
		}

		$extension->from_class = $this->baseClass;
		$extension->to_class = $extensionClass;
		$extension->execute_order = (int) $input->getOption('execute-order');
		$extension->active = !$input->getOption('inactive');
		$extension->addon_id = $this->addOnId;
		$extension->save();

		$io->success('Class extension registered in database.');
	}

	protected function getStub(): string
	{
		return 'extension.stub';
	}

	protected function getDefaultSubNamespace(): string
	{
		// Extensions mirror the base class path, so no fixed sub-namespace
		return '';
	}

	protected function qualifyClass(string $baseClass): string
	{
		$addOnClass = str_replace('/', '\\', $this->addOnId);

		return $addOnClass . '\\' . $baseClass;
	}

	protected function normalizeClassName(string $class): string
	{
		$class = ltrim($class, '\\/');
		return str_replace('/', '\\', $class);
	}

	/**
	 * Parse the "AddOnId:ClassName" colon syntax from the base-class argument.
	 *
	 * If a colon is present, extracts the add-on ID and sets it as the --addon option,
	 * then updates the base-class argument to contain only the class name.
	 */
	protected function parseBaseClassColonSyntax(InputInterface $input): void
	{
		$baseClass = $input->getArgument('base-class');
		if ($baseClass && strpos($baseClass, ':') !== false)
		{
			[$addOnId, $className] = explode(':', $baseClass, 2);
			$input->setOption('addon', $addOnId);
			$input->setArgument('base-class', $className);
		}
	}
}
