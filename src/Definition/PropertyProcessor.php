<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition;

use Nette\Utils\Arrays;
use Stepapo\Utils\Printer;
use Webovac\Generator\Generator;


class PropertyProcessor
{
	private Printer $printer;
	private Collector $collector;
	private array $commentsBefore = [];


	public function __construct(
		array $parameters,
		private Generator $generator,
	) {
		$this->printer = new Printer;
		$this->collector = new Collector($parameters);
	}


	public function setCommentsBefore($folders): void
	{
		# GETTING ORIGINAL COMMENTS
		$definition = $this->collector->getDefinition($folders);
		foreach ($definition->schemas as $schema) {
			foreach ($schema->tables as $table) {
				if (count($table->columns) === 2 && $table->primaryKey && count($table->primaryKey->columns) === 2) {
					continue;
				}
				$this->commentsBefore[$table->name] = $this->generator->getEntityComments($table);
			}
		}
	}


	public function process(array $folders): int
	{
		$count = 0;
		$start = microtime(true);
		$this->printer->printBigSeparator();
		$this->printer->printLine('Properties', 'aqua');
		$this->printer->printSeparator();
		try {
			$definition = $this->collector->getDefinition($folders);
			# GENERATING SIMPLE PROPERTIES
			foreach ($definition->schemas as $schema) {
				foreach ($schema->tables as $table) {
					if (count($table->columns) === 2 && $table->primaryKey && count($table->primaryKey->columns) === 2) {
						continue;
					}
					$this->generator->updateEntitySimple($table);
				}
			}
			# GENERATING 1:M PROPERTIES
			foreach ($definition->schemas as $schema) {
				foreach ($schema->tables as $table) {
					if (count($table->columns) === 2 && $table->primaryKey && count($table->primaryKey->columns) === 2) {
						continue;
					}
					foreach ($table->foreignKeys as $foreignKey) {
						if ($foreignKey->reverseName) {
							$this->generator->updateEntityOneHasMany($table, $foreignKey);
						}
					}
				}
			}
			# GENERATING M:M PROPERTIES
			foreach ($definition->schemas as $schema) {
				foreach ($schema->tables as $table) {
					if (count($table->columns) === 2 && $table->primaryKey && count($table->primaryKey->columns) === 2) {
						$from = Arrays::first($table->foreignKeys);
						$to = Arrays::last($table->foreignKeys);
						$this->generator->updateEntityManyHasMany($table, $from, $to, true);
						if ($to->reverseName) {
							$this->generator->updateEntityManyHasMany($table, $to, $from, false);
						}
					}
				}
			}
			# SORTING PROPERTIES
			foreach ($definition->schemas as $schema) {
				foreach ($schema->tables as $table) {
					if (count($table->columns) === 2 && $table->primaryKey && count($table->primaryKey->columns) === 2) {
						continue;
					}
					$this->generator->updateEntitySortComments($table);
				}
			}
			# GETTING UPDATED COMMENTS
			$commentsAfter = [];
			foreach ($definition->schemas as $schema) {
				foreach ($schema->tables as $table) {
					if (count($table->columns) === 2 && $table->primaryKey && count($table->primaryKey->columns) === 2) {
						continue;
					}
					$commentsAfter[$table->name] = $this->generator->getEntityComments($table);
				}
			}
			# CHECKING FOR CHANGES
			foreach ($commentsAfter as $tableName => $commentAfter) {
				if ($commentAfter !== $this->commentsBefore[$tableName]) {
					$this->printer->printText($tableName, 'white');
					$this->printer->printText(": updated properties");
					$this->printer->printOk();
					$count++;
				}
			}
			if ($count === 0) {
				$this->printer->printLine('No changes');
			}
			$this->printer->printSeparator();
			$end = microtime(true);
			$this->printer->printLine(sprintf("%d items | %0.3f s | OK", $count, $end - $start), 'lime');
		} catch (\Exception $e) {
			$this->printer->printError();
			$this->printer->printSeparator();
			$end = microtime(true);
			$this->printer->printLine(sprintf("%d items | %0.3f s | ERROR", $count, $end - $start), 'red');
			$this->printer->printLine($e->getMessage());
			$this->printer->printLine($e->getTraceAsString());
		}
		return 0;
	}
}