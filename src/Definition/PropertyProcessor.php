<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition;

use Nette\Utils\Arrays;
use Stepapo\Generator\Generator;
use Stepapo\Utils\Printer;


class PropertyProcessor
{
	private Printer $printer;
	private Collector $collector;


	public function __construct(
		private Generator $generator,
	) {
		$this->printer = new Printer;
		$this->collector = new Collector;
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
			foreach ($definition->schemas as $schema) {
				foreach ($schema->tables as $table) {
					if (count($table->columns) === 2 && $table->primaryKey && count($table->primaryKey->columns) === 2) {
						continue;
					}
					$this->printer->printText($table->name, 'white');
					$this->printer->printText(": generating simple properties");
					$this->generator->updateEntity($table);
					$this->printer->printOk();
					$count++;
				}
			}
			foreach ($definition->schemas as $schema) {
				foreach ($schema->tables as $table) {
					if (count($table->columns) === 2 && $table->primaryKey && count($table->primaryKey->columns) === 2) {
						continue;
					}
					$this->printer->printText($table->name, 'white');
					$this->printer->printText(": generating 1:m properties");
					foreach ($table->foreignKeys as $foreignKey) {
						if ($foreignKey->reverseName) {
							$this->generator->updateEntityOneHasMany($table, $foreignKey);
						}
					}
					$this->printer->printOk();
					$count++;
				}
			}
			foreach ($definition->schemas as $schema) {
				foreach ($schema->tables as $table) {
					if (count($table->columns) === 2 && $table->primaryKey && count($table->primaryKey->columns) === 2) {
						$this->printer->printText($table->name, 'white');
						$this->printer->printText(": generating m:m properties");
						$from = Arrays::first($table->foreignKeys);
						$to = Arrays::last($table->foreignKeys);
						$this->generator->updateEntityManyHasMany($from, $to, true);
						if ($to->reverseName) {
							$this->generator->updateEntityManyHasMany($to, $from);
						}
						$this->printer->printOk();
						$count++;
					}
				}
			}
			foreach ($definition->schemas as $schema) {
				foreach ($schema->tables as $table) {
					if (count($table->columns) === 2 && $table->primaryKey && count($table->primaryKey->columns) === 2) {
						continue;
					}
					$this->printer->printText($table->name, 'white');
					$this->printer->printText(": sorting properties");
					$this->generator->updateEntitySortComments($table);
					$this->printer->printOk();
					$count++;
				}
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