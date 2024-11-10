<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition;

use Nextras\Dbal\Connection;
use Nextras\Dbal\QueryException;
use Stepapo\Model\Definition\Config\Column;
use Stepapo\Model\Definition\Config\Definition;
use Stepapo\Model\Definition\Config\Foreign;
use Stepapo\Model\Definition\Config\Primary;
use Stepapo\Model\Definition\Config\Table;
use Stepapo\Model\Definition\Config\Unique;


class MysqlProcessor implements DbProcessor
{
	private string $defaultSchema = 'public';
	private Definition $definition;
	private Definition $oldDefinition;
	private int $count = 0;
	private array $createTable = [];
	private array $alterTable = [];
	private array $createIndex = [];


	public function __construct(
		private Connection $dbal,
		private Collector $collector,
		private Analyzer $analyzer,
	) {}


	/**
	 * @throws QueryException
	 */
	public function process(array $folders): int
	{
		$this->definition = $this->collector->getDefinition($folders);
		$this->oldDefinition = $this->analyzer->getDefinition();
		$this->prepare();
		$this->dbal->query("SET NAMES utf8mb4;");
		foreach ($this->createTable as $createTable) {
			$this->dbal->query($createTable);
			$this->count++;
		}
		foreach ($this->alterTable as $alterTable) {
			$this->dbal->query($alterTable);
			$this->count++;
		}
		foreach ($this->createIndex as $createIndex) {
			$this->dbal->query($createIndex);
			$this->count++;
		}
		return $this->count;
	}


	private function prepare(): void
	{
		$this->reset();
		foreach ($this->definition->tables as $table) {
			if ($table->type === 'create') {
				$this->addCreateTable($table);
			} elseif ($table->type === 'alter') {
				$this->alterTable($table);
			}
		}
	}


	private function addCreateTable(Table $table): void
	{
		$schema = $table->schema ?: $this->defaultSchema;
		$t = [];
		$t['create'] = "CREATE TABLE `$schema`.`$table->name` (";
		$c = [];
		foreach ($table->columns as $column) {
			$c[] = $this->column($table, $column);
		}
		$c[] = $this->primary($table->primaryKey);
		foreach ($table->uniqueKeys as $uniqueKey) {
			$c[] = $this->unique($table, $uniqueKey);
		}
		$t['columns'] = implode(', ', $c);
		$t['end'] = ') ENGINE = InnoDB COLLATE = utf8mb4_unicode_520_ci;';
		$this->createTable[] = implode(' ', $t);
		foreach ($table->indexes as $index) {
			$this->addCreateIndex($table, $index);
		}
		foreach ($table->foreignKeys as $foreignKey) {
			$this->addAlterTableWithForeignKey($table, $foreignKey);
		}
	}


	private function alterTable(Table $table): void
	{
		foreach ($table->columns as $column) {
			$this->addAlterTableWithColumn($table, $column);
		}
		foreach ($table->uniqueKeys as $uniqueKey) {
			$this->addAlterTableWithUnique($table, $uniqueKey);
		}
		foreach ($table->indexes as $index) {
			$this->addCreateIndex($table, $index);
		}
		foreach ($table->foreignKeys as $foreignKey) {
			$this->addAlterTableWithForeignKey($table, $foreignKey);
		}
	}


	private function addCreateIndex(Table $table, Key $key): void
	{
		$schema = $table->schema ?: $this->defaultSchema;
		$c = [];
		foreach ($key->columns as $column) {
			$c[] = "`$column`";
		}
		$this->createIndex[] = "CREATE INDEX `$key->name` ON `$schema`.`$table->name` (" . implode(", ", $c) . ");";
	}


	private function addCreateFulltext(Table $table, Column $column): void
	{
		$schema = $table->schema ?: $this->defaultSchema;
		$this->alterTable[] = "ALTER TABLE `$schema`.`$table->name` ADD FULLTEXT `{$table->name}_{$column->name}_fx` (`$column->name`);";
	}


	private function addAlterTableWithColumn(Table $table, Column $column): void
	{
		$schema = $table->schema ?: $this->defaultSchema;
		$this->alterTable[] = "ALTER TABLE `$schema`.`$table->name` ADD COLUMN " . $this->column($table, $column) . ";";
	}


	private function addAlterTableWithUnique(Table $table, Key $key): void
	{
		$schema = $table->schema ?: $this->defaultSchema;
		$this->alterTable[] = "ALTER TABLE `$schema`.`$table->name` ADD " . $this->unique($table, $key) . ";";
	}


	private function addAlterTableWithForeignKey(Table $table, Foreign $foreignKey): void
	{
		$schema = $table->schema ?: $this->defaultSchema;
		$this->alterTable[] = "ALTER TABLE `$schema`.`$table->name` ADD CONSTRAINT " . $this->foreign($table, $foreignKey) . ";";
	}


	private function column(Table $table, Column $column): string
	{
		$c = [];
		$c['name'] = "`$column->name`";
		$c['type'] = $this->getType($column->type);
		if ($column->type === 'fulltext') {
			$this->addCreateFulltext($table, $column);
		}
		if (in_array($column->type, ['string', 'text', 'fulltext'], true)) {
			$c['collate'] = 'COLLATE utf8mb4_unicode_520_ci';
		}
		$c['null'] = $column->null ? 'NULL' : 'NOT NULL';
		if ($column->default !== null) {
			$c['default'] = "DEFAULT " . $this->getDefault($column->default, $column->type);
		}
		if ($column->auto) {
			$c['auto'] = "AUTO_INCREMENT";
		}
		return implode(' ', $c);
	}


	private function primary(Primary $key): string
	{
		$c = [];
		foreach ($key->columns as $column) {
			$c[] = "`$column`";
		}
		return "PRIMARY KEY (" . implode(", ", $c) . ")";
	}


	private function unique(Table $table, Unique $key): string
	{
		$c = [];
		$n = [];
		foreach ($key->columns as $column) {
			$c[] = "`$column`";
			$n[] = $column;
		}
		return "UNIQUE INDEX `$key->name` (" . implode(", ", $c) . ")";
	}


	private function foreign(Table $table, Foreign $foreignKey): string
	{
		$foreignSchema = $foreignKey->schema ?: $this->defaultSchema;
		$k = [];
		$k['name'] = "`$foreignKey->name`";
		$k['foreignKey'] = "FOREIGN KEY (`$foreignKey->keyColumn`)";
		$k['references'] = "REFERENCES `$foreignSchema`.`$foreignKey->table` (`$foreignKey->column`)";
		$k['onUpdate'] = "ON UPDATE " . strtoupper($foreignKey->onUpdate);
		$k['onDelete'] = "ON DELETE " . strtoupper($foreignKey->onDelete);
		return implode(' ', $k);
	}


	private function getType(string $type): string
	{
		return match($type) {
			'bool' => 'tinyint',
			'int' => 'int',
			'bigint' => 'bigint',
			'string' => 'varchar(255)',
			'text' => 'text',
			'datetime' => 'timestamp',
			'float' => 'float',
			'fulltext' => 'text',
		};
	}


	private function getDefault(mixed $default, string $type): mixed
	{
		return match($default) {
			'now' => "CURRENT_TIMESTAMP",
			default => match($type) {
				'bool' => $default ? 1 : 0,
				'int' => $default,
				'bigint' => $default,
				'string' => "'$default'",
				'text' => "'$default'",
			}
		};
	}


	private function reset(): void
	{
		$this->count = 0;
		$this->createTable = [];
		$this->createIndex = [];
		$this->alterTable = [];
	}


	public function setDefaultSchema(string $defaultSchema): self
	{
		$this->defaultSchema = $defaultSchema;
		return $this;
	}
}