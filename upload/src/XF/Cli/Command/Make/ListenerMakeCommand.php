<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\CodeEvent;
use XF\Entity\CodeEventListener;
use XF\Finder\CodeEventFinder;
use XF\Finder\CodeEventListenerFinder;

class ListenerMakeCommand extends AbstractMakeCommand
{
	protected const METHOD_FAILED = 0;
	protected const METHOD_UNCHANGED = 1;
	protected const METHOD_APPENDED = 2;

	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:listener')
			->setDescription('Create a listener class and register an event listener')
			->addArgument(
				'event',
				InputArgument::REQUIRED,
				'The event to listen for (e.g. "Demo/Thing:app_setup" or "app_setup" with --addon)'
			)
			->addOption(
				'class',
				'c',
				InputOption::VALUE_REQUIRED,
				'Callback class name relative to add-on namespace',
				'Listener'
			)
			->addOption(
				'method',
				'm',
				InputOption::VALUE_REQUIRED,
				'Callback method name (default: camelCase of event_id)'
			)
			->addOption(
				'description',
				'd',
				InputOption::VALUE_REQUIRED,
				'Description of the listener',
				''
			)
			->addOption(
				'hint',
				null,
				InputOption::VALUE_REQUIRED,
				'Hint for the listener (e.g. class name for entity_structure event)',
				''
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
				'Create the listener as inactive'
			)
			->addOption(
				'event-description',
				null,
				InputOption::VALUE_REQUIRED,
				'Create a missing code event with this description'
			)
			->addOption(
				'event-argument',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Repeatable CSV argument for a missing event: name,type,description'
			);
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		$this->resolveAddOnFromArgument($input, 'event');

		$this->interactAddOn($input, $io, 'event', false);

		if (!$input->getArgument('event'))
		{
			$events = $this->getAvailableEvents();
			if ($events)
			{
				$eventId = $io->choice('Which event do you want to listen for?', $events);
			}
			else
			{
				$eventId = $io->ask('Enter the event ID (e.g. app_setup)');
			}
			$input->setArgument('event', $eventId);
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$this->resolveAddOnFromArgument($input, 'event');

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

		$eventId = (string) $input->getArgument('event');
		$event = $this->getOrCreateEvent($eventId, $input, $output, $io);
		if (!$event)
		{
			return Command::FAILURE;
		}

		if (!$this->createOrUpdateListener($eventId, $event, $input, $io))
		{
			return Command::FAILURE;
		}

		return Command::SUCCESS;
	}

	protected function getOrCreateEvent(
		string $eventId,
		InputInterface $input,
		OutputInterface $output,
		SymfonyStyle $io
	): ?CodeEvent
	{
		$event = $this->getEvent($eventId);
		if ($event)
		{
			return $event;
		}

		$eventDescription = $input->getOption('event-description');
		if ($eventDescription === null)
		{
			$io->error("The event '$eventId' does not exist. Use --event-description to create it.");
			return null;
		}

		if (!$this->runMakeCommand('xf-make:code-event', [
			'id' => $eventId,
			'--addon' => $this->addOnId,
			'--description' => $eventDescription,
			'--argument' => $input->getOption('event-argument'),
		], $output, $io))
		{
			return null;
		}

		$event = $this->getEvent($eventId);
		if (!$event)
		{
			$io->error("Code event '$eventId' could not be loaded after it was created.");
		}

		return $event;
	}

	protected function createOrUpdateListener(
		string $eventId,
		CodeEvent $event,
		InputInterface $input,
		SymfonyStyle $io
	): bool
	{
		$className = $input->getOption('class');
		$methodName = $input->getOption('method') ?: $this->eventToMethodName($eventId);
		if (!$this->validateClassPath($className, $io, 'Listener class'))
		{
			return false;
		}
		if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $methodName))
		{
			$io->error('Listener method must be a valid PHP method name.');
			return false;
		}
		$force = $input->getOption('force');
		$fullClassName = str_replace('/', '\\', $this->addOnId) . '\\' . str_replace('/', '\\', $className);
		$hint = $input->getOption('hint') ?: '';
		$existingListener = $this->getExistingListener($eventId, $fullClassName, $methodName, $hint, $this->addOnId);
		if ($existingListener && !$force)
		{
			$io->note("Listener for event '$eventId' with callback $fullClassName::$methodName already registered.");
			$io->note('Use --force to update its configured values.');
			return true;
		}

		$classWasLoaded = class_exists($fullClassName, false);
		$filePath = $this->ensureListenerClassExists($io, $className);
		if ($filePath === false)
		{
			return false;
		}

		$methodResult = $this->addMethodToClass($io, $filePath, $methodName, $event, $force);
		if ($methodResult === self::METHOD_FAILED)
		{
			return false;
		}

		$this->registerListener(
			$input,
			$eventId,
			$fullClassName,
			$methodName,
			$classWasLoaded && $methodResult === self::METHOD_APPENDED,
			$existingListener
		);
		$io->success("Listener registered: $fullClassName::$methodName for event '$eventId'");

		return true;
	}

	/**
	 * @return string|false
	 */
	protected function ensureListenerClassExists(SymfonyStyle $io, string $className)
	{
		$filePath = $this->getClassFilePath($className);

		if (file_exists($filePath))
		{
			$io->note("Listener class already exists at $filePath");
			return $filePath;
		}

		$namespace = str_replace('/', '\\', $this->addOnId);
		$shortClassName = $className;

		if (strpos($className, '/') !== false || strpos($className, '\\') !== false)
		{
			$parts = preg_split('/[\/\\\\]/', $className);
			$shortClassName = array_pop($parts);
			$namespace .= '\\' . implode('\\', $parts);
		}

		$stubPath = $this->resolveStubPath('listener.stub');
		$stub = file_get_contents($stubPath);

		if ($stub === false)
		{
			throw new \RuntimeException("Failed to load stub file: $stubPath");
		}

		$stub = str_replace(
			[
				'{{ namespace }}',
				'{{ class }}',
			],
			[
				$namespace,
				$shortClassName,
			],
			$stub
		);

		$this->writeFile($filePath, $stub);
		$io->success("Listener class created at $filePath");

		return $filePath;
	}

	protected function addMethodToClass(SymfonyStyle $io, string $filePath, string $methodName, CodeEvent $event, bool $force): int
	{
		$content = file_get_contents($filePath);
		if ($content === false)
		{
			$io->error("Could not read {$filePath}.");
			return self::METHOD_FAILED;
		}

		if (preg_match('/function\s+' . preg_quote($methodName, '/') . '\s*\(/i', $content))
		{
			if (!$force)
			{
				$io->error("Method '$methodName' already exists in $filePath");
				$io->note('Use --force to skip this check (method will NOT be overwritten).');
				return self::METHOD_FAILED;
			}
			$io->note("Method '$methodName' already exists, skipping method creation.");
			return self::METHOD_UNCHANGED;
		}

		$stubPath = $this->resolveStubPath('listener.method.stub');
		$methodStub = file_get_contents($stubPath);
		$methodStub = str_replace('{{ method }}', $methodName, $methodStub);

		$params = $event->callback_signature;
		$methodStub = str_replace('{{ params }}', $params, $methodStub);

		$methodStub = trim($methodStub);

		$methodStub = "\t" . str_replace("\n", "\n\t", $methodStub);
		$methodStub = preg_replace("/\n\t$/", "\n", $methodStub);

		$lastBrace = strrpos($content, '}');
		if ($lastBrace === false)
		{
			$io->error("Could not parse $filePath - missing closing brace.");
			return self::METHOD_FAILED;
		}

		$beforeBrace = rtrim(substr($content, 0, $lastBrace));

		$hasExistingMethods = preg_match('/function\s+\w+\s*\(/i', $beforeBrace);
		$separator = $hasExistingMethods ? "\n\n" : "\n";

		$newContent = $beforeBrace . $separator . $methodStub . "\n}\n";

		$this->writeFile($filePath, $newContent);

		$io->success("Method '$methodName' added to $filePath");
		return self::METHOD_APPENDED;
	}

	protected function registerListener(
		InputInterface $input,
		string $eventId,
		string $className,
		string $methodName,
		bool $skipCallbackValidation,
		?CodeEventListener $listener = null
	): void
	{
		$listener = $listener ?: \XF::em()->create(CodeEventListener::class);
		$listener->event_id = $eventId;
		$listener->callback_class = $className;
		$listener->callback_method = $methodName;
		$listener->execute_order = (int) $input->getOption('execute-order');
		$listener->description = $input->getOption('description') ?: '';
		$listener->hint = $input->getOption('hint') ?: '';
		$listener->active = !$input->getOption('inactive');
		$listener->addon_id = $this->addOnId;
		if ($skipCallbackValidation)
		{
			$listener->setOption('skip_callback_validation', true);
		}
		$listener->save();
	}

	protected function getClassFilePath(string $className): string
	{
		$classPath = str_replace('\\', '/', $className) . '.php';
		return $this->getAddOnDirectory() . '/' . $classPath;
	}

	protected function getEvent(string $eventId): ?CodeEvent
	{
		return \XF::finder(CodeEventFinder::class)
			->where('event_id', $eventId)
			->fetchOne();
	}

	protected function getAvailableEvents(): array
	{
		$events = \XF::finder(CodeEventFinder::class)
			->order('event_id')
			->fetch();

		$result = [];
		foreach ($events AS $event)
		{
			$result[$event->event_id] = $event->event_id;
		}

		return $result;
	}

	protected function getExistingListener(
		string $eventId,
		string $className,
		string $methodName,
		string $hint,
		string $addOnId
	): ?CodeEventListener
	{
		return \XF::finder(CodeEventListenerFinder::class)
			->where([
				'event_id' => $eventId,
				'callback_class' => $className,
				'callback_method' => $methodName,
				'hint' => $hint,
				'addon_id' => $addOnId,
			])
			->fetchOne();
	}

	protected function eventToMethodName(string $eventId): string
	{
		return lcfirst(str_replace('_', '', ucwords($eventId, '_')));
	}
}
