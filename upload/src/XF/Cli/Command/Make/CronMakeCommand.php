<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\CronEntry;
use XF\Finder\CronEntryFinder;

use function array_slice, count, in_array, strlen;

class CronMakeCommand extends AbstractMakeCommand
{
	protected const SCHEDULE_PRESETS = [
		'hourly' => [
			'day_type' => 'dom',
			'dom' => [-1],
			'hours' => [-1],
			'minutes' => [10],
		],
		'daily' => [
			'day_type' => 'dom',
			'dom' => [-1],
			'hours' => [0],
			'minutes' => [0],
		],
		'weekly' => [
			'day_type' => 'dow',
			'dow' => [0],
			'hours' => [0],
			'minutes' => [0],
		],
		'monthly' => [
			'day_type' => 'dom',
			'dom' => [1],
			'hours' => [0],
			'minutes' => [0],
		],
	];

	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:cron')
			->setDescription('Create a cron class and register a cron entry')
			->addArgument(
				'entry',
				InputArgument::REQUIRED,
				'The cron entry ID (e.g. "Demo/Thing:myCleanUp" or "myCleanUp" with --addon)'
			)
			->addOption(
				'class',
				'c',
				InputOption::VALUE_REQUIRED,
				'Callback class name relative to add-on namespace',
				'Cron'
			)
			->addOption(
				'method',
				'm',
				InputOption::VALUE_REQUIRED,
				'Callback method name (default: same as entry_id)'
			)
			->addOption(
				'title',
				't',
				InputOption::VALUE_REQUIRED,
				'Title phrase text (default: humanized entry_id)'
			)
			->addOption(
				'schedule',
				's',
				InputOption::VALUE_REQUIRED,
				'Schedule preset: hourly, daily, weekly, monthly'
			)
			->addOption(
				'hours',
				null,
				InputOption::VALUE_REQUIRED,
				'Hours to run (comma-separated, 0-23, or -1 for any)'
			)
			->addOption(
				'minutes',
				null,
				InputOption::VALUE_REQUIRED,
				'Minutes to run (comma-separated, 0-59, or -1 for any)'
			)
			->addOption(
				'dom',
				null,
				InputOption::VALUE_REQUIRED,
				'Days of month to run (comma-separated, 1-31, or -1 for any)'
			)
			->addOption(
				'dow',
				null,
				InputOption::VALUE_REQUIRED,
				'Days of week to run (comma-separated, 0-6 where 0=Sunday, or -1 for any)'
			)
			->addOption(
				'inactive',
				null,
				InputOption::VALUE_NONE,
				'Create the cron entry as inactive'
			);
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		$this->resolveAddOnFromArgument($input, 'entry');

		$this->interactAddOn($input, $io, 'entry');

		if (!$input->getArgument('entry'))
		{
			$entryId = $io->ask(
				'Enter the cron entry ID (e.g. myCleanUp)',
				null,
				function ($value)
				{
					if (empty($value))
					{
						throw new \InvalidArgumentException('Entry ID cannot be empty.');
					}
					return $value;
				}
			);
			$input->setArgument('entry', $entryId);
		}

		if (!$this->hasScheduleOptions($input))
		{
			$this->promptForSchedule($input, $io);
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$this->resolveAddOnFromArgument($input, 'entry');

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

		$entryId = $this->normalizeEntryId($input->getArgument('entry'));

		if (!preg_match('/^[a-zA-Z0-9]+$/', $entryId))
		{
			$io->error('Entry ID must be alphanumeric (letters and numbers only).');
			$io->note("Input was normalized to: '$entryId'");
			return Command::FAILURE;
		}

		if (strlen($entryId) > 25)
		{
			$io->error('Entry ID must be 25 characters or less.');
			return Command::FAILURE;
		}

		$existingEntry = $this->getExistingCronEntry($entryId);
		if ($existingEntry)
		{
			if (!$this->validateEntityOwner($existingEntry, $io))
			{
				return Command::FAILURE;
			}
			if (!$input->getOption('force'))
			{
				$io->error("Cron entry '$entryId' already exists.");
				$io->note('Use --force to update the existing entry.');
				return Command::FAILURE;
			}
		}

		$runRules = $this->buildRunRules($input, $io);
		if ($runRules === null)
		{
			return Command::FAILURE;
		}

		$className = $input->getOption('class');
		$methodName = $input->getOption('method') ?: $entryId;
		if (!$this->validateClassPath($className, $io, 'Cron class'))
		{
			return Command::FAILURE;
		}
		if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $methodName))
		{
			$io->error('Cron method must be a valid PHP method name.');
			return Command::FAILURE;
		}
		$force = $input->getOption('force');

		$filePath = $this->ensureCronClassExists($io, $className);
		if ($filePath === false)
		{
			return Command::FAILURE;
		}

		$methodAdded = $this->addMethodToClass($io, $filePath, $methodName, $force);
		if ($methodAdded === false)
		{
			return Command::FAILURE;
		}

		$fullClassName = str_replace('/', '\\', $this->addOnId) . '\\' . str_replace('/', '\\', $className);
		$title = $input->getOption('title') ?: $this->humanizeEntryId($entryId);

		$this->registerCronEntry($input, $entryId, $fullClassName, $methodName, $title, $runRules);

		$scheduleDesc = $this->describeSchedule($runRules);
		$io->success("Cron entry '$entryId' registered ($scheduleDesc)");

		return Command::SUCCESS;
	}

	protected function hasScheduleOptions(InputInterface $input): bool
	{
		return $input->getOption('schedule') !== null
			|| $input->getOption('hours') !== null
			|| $input->getOption('minutes') !== null
			|| $input->getOption('dom') !== null
			|| $input->getOption('dow') !== null;
	}

	protected function promptForSchedule(InputInterface $input, SymfonyStyle $io): void
	{
		$choices = [
			'hourly' => 'Every hour (at :10)',
			'daily' => 'Once per day (at midnight)',
			'weekly' => 'Once per week (Sunday at midnight)',
			'monthly' => 'Once per month (1st at midnight)',
			'custom' => 'Custom schedule...',
		];

		$schedule = $io->choice('How often should this task run?', $choices, 'daily');

		if ($schedule === 'custom')
		{
			$this->promptForCustomSchedule($input, $io);
		}
		else
		{
			$input->setOption('schedule', $schedule);
		}
	}

	protected function promptForCustomSchedule(InputInterface $input, SymfonyStyle $io): void
	{
		$minutes = $io->ask(
			'Which minutes? (comma-separated, 0-59, or -1 for any)',
			'0',
			function ($value)
			{
				return $this->validateTimeInput($value, 0, 59);
			}
		);
		$input->setOption('minutes', $minutes);

		$hours = $io->ask(
			'Which hours? (comma-separated, 0-23, or -1 for any)',
			'0',
			function ($value)
			{
				return $this->validateTimeInput($value, 0, 23);
			}
		);
		$input->setOption('hours', $hours);

		$dayType = $io->choice(
			'Run on specific days of the month or week?',
			[
				'dom' => 'Days of month (1-31)',
				'dow' => 'Days of week (0=Sunday through 6=Saturday)',
			],
			'dom'
		);

		if ($dayType === 'dom')
		{
			$dom = $io->ask(
				'Which days of the month? (comma-separated, 1-31, or -1 for any)',
				'-1',
				function ($value)
				{
					return $this->validateTimeInput($value, 1, 31);
				}
			);
			$input->setOption('dom', $dom);
		}
		else
		{
			$dow = $io->ask(
				'Which days of the week? (comma-separated, 0-6 where 0=Sunday, or -1 for any)',
				'0',
				function ($value)
				{
					return $this->validateTimeInput($value, 0, 6);
				}
			);
			$input->setOption('dow', $dow);
		}
	}

	protected function validateTimeInput(string $value, int $min, int $max): string
	{
		$parts = array_map('trim', explode(',', $value));
		foreach ($parts AS $part)
		{
			if (!preg_match('/^-?\d+$/', $part))
			{
				throw new \InvalidArgumentException("Invalid numeric value '{$part}'");
			}
			$intVal = (int) $part;
			if ($intVal === -1)
			{
				continue;
			}
			if ($intVal < $min || $intVal > $max)
			{
				throw new \InvalidArgumentException(
					"Value {$intVal} is out of range ({$min}-{$max})"
				);
			}
		}
		return $value;
	}

	protected function buildRunRules(InputInterface $input, SymfonyStyle $io): ?array
	{
		$schedule = $input->getOption('schedule');
		$dom = $input->getOption('dom');
		$dow = $input->getOption('dow');
		if ($dom !== null && $dow !== null)
		{
			$io->error('Use either --dom or --dow, not both.');
			return null;
		}

		try
		{
			if ($schedule !== null)
			{
				if (!isset(self::SCHEDULE_PRESETS[$schedule]))
				{
					$io->error("Invalid schedule preset: '$schedule'. Valid options: hourly, daily, weekly, monthly");
					return null;
				}

				$runRules = self::SCHEDULE_PRESETS[$schedule];

				if ($input->getOption('hours') !== null)
				{
					$runRules['hours'] = $this->parseIntList($input->getOption('hours'), 0, 23);
				}
				if ($input->getOption('minutes') !== null)
				{
					$runRules['minutes'] = $this->parseIntList($input->getOption('minutes'), 0, 59);
				}
				if ($dom !== null)
				{
					unset($runRules['dow']);
					$runRules['day_type'] = 'dom';
					$runRules['dom'] = $this->parseIntList($dom, 1, 31);
				}
				else if ($dow !== null)
				{
					unset($runRules['dom']);
					$runRules['day_type'] = 'dow';
					$runRules['dow'] = $this->parseIntList($dow, 0, 6);
				}

				return $runRules;
			}

			$hours = $input->getOption('hours');
			$minutes = $input->getOption('minutes');

			if ($minutes === null && $hours === null && $dom === null && $dow === null)
			{
				$io->error('Schedule is required. Use --schedule or specify timing options (--minutes, --hours, --dom, --dow).');
				return null;
			}

			$runRules = [
				'minutes' => $this->parseIntList($minutes ?? '0', 0, 59),
			];

			$runRules['hours'] = $hours !== null
				? $this->parseIntList($hours, 0, 23)
				: [-1];

			if ($dow !== null)
			{
				$runRules['day_type'] = 'dow';
				$runRules['dow'] = $this->parseIntList($dow, 0, 6);
			}
			else
			{
				$runRules['day_type'] = 'dom';
				$runRules['dom'] = $dom !== null ? $this->parseIntList($dom, 1, 31) : [-1];
			}

			return $runRules;
		}
		catch (\InvalidArgumentException $e)
		{
			$io->error($e->getMessage());
			return null;
		}
	}

	protected function parseIntList(string $value, int $min, int $max): array
	{
		$this->validateTimeInput($value, $min, $max);
		return array_map('intval', array_map('trim', explode(',', $value)));
	}

	/**
	 * @return string|false
	 */
	protected function ensureCronClassExists(SymfonyStyle $io, string $className)
	{
		$filePath = $this->getClassFilePath($className);

		if (file_exists($filePath))
		{
			$io->note("Cron class already exists at $filePath");
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

		$stub = file_get_contents($this->resolveStubPath('cron.stub'));
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

		$this->ensureDirectory($filePath);

		$this->writeFile($filePath, $stub);
		$io->success("Cron class created at $filePath");

		return $filePath;
	}

	protected function addMethodToClass(SymfonyStyle $io, string $filePath, string $methodName, bool $force): bool
	{
		$content = file_get_contents($filePath);
		if ($content === false)
		{
			$io->error("Could not read {$filePath}.");
			return false;
		}

		if (preg_match('/function\s+' . preg_quote($methodName, '/') . '\s*\(/i', $content))
		{
			if (!$force)
			{
				$io->error("Method '$methodName' already exists in $filePath");
				$io->note('Use --force to skip this check (method will NOT be overwritten).');
				return false;
			}
			$io->note("Method '$methodName' already exists, skipping method creation.");
			return true;
		}

		$stubPath = $this->resolveStubPath('cron.method.stub');
		$methodStub = file_get_contents($stubPath);
		$methodStub = str_replace('{{ method }}', $methodName, $methodStub);

		$methodStub = trim($methodStub);
		$methodStub = "\t" . str_replace("\n", "\n\t", $methodStub);
		$methodStub = preg_replace("/\n\t$/", "\n", $methodStub);

		$lastBrace = strrpos($content, '}');
		if ($lastBrace === false)
		{
			$io->error("Could not parse $filePath - missing closing brace.");
			return false;
		}

		$beforeBrace = rtrim(substr($content, 0, $lastBrace));

		$hasExistingMethods = preg_match('/function\s+\w+\s*\(/i', $beforeBrace);
		$separator = $hasExistingMethods ? "\n\n" : "\n";

		$newContent = $beforeBrace . $separator . $methodStub . "\n}\n";

		$this->writeFile($filePath, $newContent);

		$io->success("Method '$methodName' added to $filePath");
		return true;
	}

	protected function registerCronEntry(
		InputInterface $input,
		string $entryId,
		string $className,
		string $methodName,
		string $title,
		array $runRules
	): void
	{
		$db = \XF::db();
		$db->beginTransaction();

		try
		{
			$cronEntry = $this->getExistingCronEntry($entryId) ?: \XF::em()->create(CronEntry::class);
			$cronEntry->entry_id = $entryId;
			$cronEntry->cron_class = $className;
			$cronEntry->cron_method = $methodName;
			$cronEntry->run_rules = $runRules;
			$cronEntry->active = !$input->getOption('inactive');
			$cronEntry->addon_id = $this->addOnId;
			$cronEntry->save();

			$masterPhrase = $cronEntry->getMasterPhrase();
			$masterPhrase->phrase_text = $title;
			$masterPhrase->save();

			$db->commit();
		}
		catch (\Exception $e)
		{
			$db->rollback();
			throw $e;
		}
	}

	protected function getClassFilePath(string $className): string
	{
		$classPath = str_replace('\\', '/', $className) . '.php';
		return $this->getAddOnDirectory() . '/' . $classPath;
	}

	protected function getExistingCronEntry(string $entryId): ?CronEntry
	{
		return \XF::finder(CronEntryFinder::class)
			->where('entry_id', $entryId)
			->fetchOne();
	}

	protected function normalizeEntryId(string $entryId): string
	{
		$entryId = str_replace('-', '_', $entryId);

		if (strpos($entryId, '_') !== false)
		{
			$parts = explode('_', $entryId);
			$entryId = $parts[0] . implode('', array_map('ucfirst', array_slice($parts, 1)));
		}

		return lcfirst($entryId);
	}

	protected function humanizeEntryId(string $entryId): string
	{
		$parts = preg_split('/(?=[A-Z])/', $entryId, -1, PREG_SPLIT_NO_EMPTY);

		$humanized = strtolower(implode(' ', $parts));

		return ucfirst($humanized);
	}

	protected function describeSchedule(array $runRules): string
	{
		$hours = $runRules['hours'] ?? [-1];
		$minutes = $runRules['minutes'] ?? [0];
		$dayType = $runRules['day_type'] ?? 'dom';

		$anyHour = in_array(-1, $hours, true);
		if (in_array(-1, $minutes, true))
		{
			if ($anyHour)
			{
				$timeStr = 'every minute';
			}
			else
			{
				$hourLabel = count($hours) === 1 ? 'hour' : 'hours';
				$timeStr = "every minute during {$hourLabel} " . implode(', ', $hours);
			}
		}
		else if ($anyHour)
		{
			$minuteStrings = array_map(function ($minute)
			{
				return ':' . str_pad((string) $minute, 2, '0', STR_PAD_LEFT);
			}, $minutes);
			$timeStr = 'hourly at ' . implode(', ', $minuteStrings);
		}
		else
		{
			$times = [];
			foreach ($hours AS $hour)
			{
				foreach ($minutes AS $minute)
				{
					$times[] = $hour . ':' . str_pad((string) $minute, 2, '0', STR_PAD_LEFT);
				}
			}
			$timeStr = implode(', ', $times);
		}

		if ($dayType === 'dow')
		{
			$days = $runRules['dow'] ?? [0];
			$dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
			if (in_array(-1, $days))
			{
				return $timeStr;
			}
			$dayStr = implode(', ', array_map(function ($d) use ($dayNames)
			{
				$d = (int) $d;
				return ($d >= 0 && $d <= 6) ? $dayNames[$d] : (string) $d;
			}, $days));
			return "{$dayStr} at {$timeStr}";
		}
		else
		{
			$days = $runRules['dom'] ?? [-1];
			if (in_array(-1, $days))
			{
				return $timeStr;
			}
			$dayStr = implode(', ', $days);
			return "day {$dayStr} of month at {$timeStr}";
		}
	}
}
