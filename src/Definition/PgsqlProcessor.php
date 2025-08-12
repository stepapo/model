<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition;

use Nette\InvalidArgumentException;
use Nextras\Dbal\Connection;
use Stepapo\Model\Definition\Config\Column;
use Stepapo\Model\Definition\Config\Definition;
use Stepapo\Model\Definition\Config\Foreign;
use Stepapo\Model\Definition\Config\Index;
use Stepapo\Model\Definition\Config\Primary;
use Stepapo\Model\Definition\Config\Schema;
use Stepapo\Model\Definition\Config\Table;
use Stepapo\Model\Definition\Config\Unique;
use Stepapo\Utils\Printer;


class PgsqlProcessor implements DbProcessor
{
	private string $defaultSchema = 'public';
	private Definition $definition;
	private Definition $oldDefinition;
	private Printer $printer;
	private array $steps = [
		'createSchema' => [],
		'dropSequence' => [],
		'createSequence' => [],
		'createTable' => [],
		'alterTable' => [],
		'alterSequence' => [],
		'createIndex' => [],
		'alterTableDrop' => [],
		'alterTableAdd' => [],
		'dropSchema' => [],
		'dropTable' => [],
		'dropIndex' => [],
	];


	public function __construct(
		private array $schemas,
		private Connection $dbal,
		private Collector $collector,
		private Comparator $comparator,
		private Analyzer $analyzer,
	) {
		$this->printer = new Printer;
	}


	public function process(array $folders): int
	{
		$start = microtime(true);
		$this->printer->printBigSeparator();
		$this->printer->printLine('Definitions', 'aqua');
		$this->printer->printSeparator();
		foreach ($_SERVER['argv'] as $arg) {
			if ($arg === '--reset') {
				foreach ($this->schemas as $schema) {
					$this->dbal->query("DROP SCHEMA IF EXISTS %table CASCADE", $schema);
					$this->dbal->query("CREATE SCHEMA %table", $schema);
				}
			}
		}
		$this->definition = $this->collector->getDefinition($folders);
		$this->oldDefinition = $this->analyzer->getDefinition($this->schemas);
		$this->prepare();
		$count = 0;
		try {
			/** @var Query[] $queries */
			foreach ($this->steps as $queries) {
				foreach ($queries as $query) {
					if ($query->text) {
						$this->printer->printText($query->table, 'white');
						$this->printer->printText(": $query->text");
						if ($query->item) {
							$this->printer->printText(" $query->item", 'white');
						}
					}
					$this->dbal->query($query->sql);
					if ($query->text) {
						$this->printer->printOk();
					}
					$count++;
				}
			}
			if ($count === 0) {
				$this->printer->printLine('No changes');
			}
			$this->printer->printSeparator();
			$end = microtime(true);
			$this->printer->printLine(sprintf("%d queries | %0.3f s | OK", $count, $end - $start), 'lime');
		} catch (\Exception $e) {
			$this->printer->printError();
			$this->printer->printSeparator();
			$end = microtime(true);
			$this->printer->printLine(sprintf("%d queries | %0.3f s | ERROR in query '%s'", $count, $end - $start, $query->sql), 'red');
			$this->printer->printLine($e->getMessage());
			$this->printer->printLine($e->getTraceAsString());
		}

		return $count;
	}


	private function prepare(): void
	{
		$this->reset();
		$diff = $this->comparator->compare($this->definition, $this->oldDefinition);
		foreach ($diff as $type => $schemas) {
			$this->processType($type, $schemas);
		}
	}


	private function processType(string $type, array $schemas)
	{
		foreach ($schemas as $schemaName => $setup) {
			/** @var Schema $schema */
			$schema = $this->definition->schemas[$schemaName] ?? $this->oldDefinition->schemas[$schemaName];
			$this->processSchema($type, $schema, $setup);
		}
	}


	private function processSchema(string $type, Schema $schema, array $setup)
	{
		if (isset($setup['all']) && $setup['all'] === true) {
			(fn() => match ($type) {
				'create' => $this->createSchema($schema),
				'remove' => $this->removeSchema($schema),
				'update' => throw new InvalidArgumentException("Type 'update' cannot be used with 'all' in schema '$schema->name'."),
			})();
			return;
		}
		foreach ($setup as $tableName => $s) {
			/** @var Table $table */
			$table = $schema->tables[$tableName] ?? $this->oldDefinition->schemas[$schema->name]->tables[$tableName];
			$this->processTable($type, $schema, $table, $s);
		}
	}


	private function processTable(string $type, Schema $schema, Table $table, array $setup)
	{
		if (isset($setup['all']) && $setup['all'] === true) {
			(fn() => match ($type) {
				'create' => $this->createTable($schema, $table),
				'remove' => $this->removeTable($schema, $table),
				'update' => throw new InvalidArgumentException("Type 'update' cannot be used with 'all' in table '$schema->name.$table->name'."),
			})();
			return;
		}
		if (isset($setup['primaryKey']) && $setup['primaryKey'] === true) {
			(fn() => match ($type) {
				'create' => throw new InvalidArgumentException("Type 'create' cannot be used with 'primaryKey' in table '$table->name'."),
				'remove' => throw new InvalidArgumentException("Type 'remove' cannot be used with 'primaryKey' in table '$table->name'."),
				'update' => $this->updatePrimary($schema, $table),
			})();
			return;
		}
		if (isset($setup['columns'])) {
			foreach ($setup['columns'] as $columnName) {
				$column = $table->columns[$columnName] ?? $this->oldDefinition->schemas[$schema->name]->tables[$table->name]->columns[$columnName];
				$this->processColumn($type, $schema, $table, $column);
			}
		}
		if (isset($setup['foreignKeys'])) {
			foreach ($setup['foreignKeys'] as $foreignKeyName) {
				$foreign = $table->foreignKeys[$foreignKeyName] ?? $this->oldDefinition->schemas[$schema->name]->tables[$table->name]->foreignKeys[$foreignKeyName];
				$this->processForeignKey($type, $schema, $table, $foreign);
			}
		}
		if (isset($setup['indexes'])) {
			foreach ($setup['indexes'] as $indexName) {
				$index = $table->indexes[$indexName] ?? $this->oldDefinition->schemas[$schema->name]->tables[$table->name]->indexes[$indexName];
				$this->processIndex($type, $schema, $table, $index);
			}
		}
		if (isset($setup['uniqueKeys'])) {
			foreach ($setup['uniqueKeys'] as $uniqueKeyName) {
				$unique = $table->uniqueKeys[$uniqueKeyName] ?? $this->oldDefinition->schemas[$schema->name]->tables[$table->name]->uniqueKeys[$uniqueKeyName];
				$this->processUniqueKey($type, $schema, $table, $unique);
			}
		}
	}


	private function processColumn(string $type, Schema $schema, Table $table, Column $column)
	{
		(fn() => match ($type) {
			'create' => $this->createColumn($schema, $table, $column),
			'remove' => $this->removeColumn($schema, $table, $column),
			'update' => $this->updateColumn($schema, $table, $column),
		})();
	}


	private function processForeignKey(string $type, Schema $schema, Table $table, Foreign $foreignKey)
	{
		(fn() => match ($type) {
			'create' => $this->createForeign($schema, $table, $foreignKey),
			'remove' => $this->removeForeign($schema, $table, $foreignKey),
			'update' => $this->updateForeign($schema, $table, $foreignKey),
		})();
	}


	private function processIndex(string $type, Schema $schema, Table $table, Index $index)
	{
		(fn() => match ($type) {
			'create' => $this->createIndex($schema, $table, $index),
			'update' => $this->updateIndex($schema, $table, $index),
			'remove' => $this->removeIndex($schema, $table, $index),
		})();
	}


	private function processUniqueKey(string $type, Schema $schema, Table $table, Unique $uniqueKey)
	{
		(fn() => match ($type) {
			'create' => $this->createUnique($schema, $table, $uniqueKey),
			'update' => $this->updateUnique($schema, $table, $uniqueKey),
			'remove' => $this->removeUnique($schema, $table, $uniqueKey),
		})();
	}


	private function addQuery(Query $query): void
	{
		if (!isset($this->steps[$query->step])) {
			throw new InvalidArgumentException("Step '$query->step' is not defined");
		}
		$this->steps[$query->step][] = $query;
	}


	private function createSequence(Schema $schema, Table $table): void
	{
		$this->removeSequence($schema, $table);
		$this->addQuery(new Query(
			'createSequence',
			"CREATE SEQUENCE \"{$table->name}_id_seq\"",
		));
	}


	private function alterSequence(Schema $schema, Table $table, Column $column): void
	{
		$this->addQuery(new Query(
			'alterSequence',
			"ALTER SEQUENCE \"{$table->name}_id_seq\" OWNED BY \"{$table->name}\".\"{$column->name}\"",
		));
	}


	private function removeSequence(Schema $schema, Table $table): void
	{
		$this->addQuery(new Query(
			'dropSequence',
			"DROP SEQUENCE IF EXISTS \"{$table->name}_id_seq\" CASCADE",
		));
	}


	private function createSchema(Schema $schema): void
	{
		$this->addQuery(new Query(
			'createSchema',
			"CREATE SCHEMA IF NOT EXISTS \"{$schema->name}\"",
			'creating schema',
			$schema->name,
		));
	}


	private function removeSchema(Schema $schema): void
	{
		$this->addQuery(new Query(
			'dropSchema',
			"DROP SCHEMA IF EXISTS \"{$schema->name}\";",
			'removing schema',
			$schema->name,
		));
	}


	private function createTable(Schema $schema, Table $table): void
	{
		$t = [];
		$t['create'] = "CREATE TABLE \"$schema->name\".\"$table->name\" (";
		$c = [];
		foreach ($table->columns as $column) {
			$c[] = $this->column($schema, $table, $column);
		}
		$c[] = $this->primary($table->primaryKey);
		$t['columns'] = implode(', ', $c);
		$t['end'] = ')';
		$this->addQuery(new Query(
			'createTable',
			implode(' ', $t),
			"$schema->name.$table->name",
			'creating table',
		));

		foreach ($table->uniqueKeys as $index) {
			$this->createUnique($schema, $table, $index);
		}
		foreach ($table->indexes as $index) {
			$this->createIndex($schema, $table, $index);
		}
		foreach ($table->foreignKeys as $foreignKey) {
			$this->createForeign($schema, $table, $foreignKey);
		}
	}


	private function removeTable(Schema $schema, Table $table): void
	{
		$this->addQuery(new Query(
			'dropTable',
			"DROP TABLE IF EXISTS \"$schema->name\".\"$table->name\" CASCADE",
			"$schema->name.$table->name",
			'removing'
		));
		$this->removeSequence($schema, $table);
	}


	private function updatePrimary(Schema $schema, Table $table): void
	{
		$this->addQuery(new Query(
			'alterTable',
			"ALTER TABLE \"$schema->name\".\"$table->name\" DROP CONSTRAINT \"$table->name" . "_pkey\", ADD " . $this->primary($table->primaryKey),
			"$schema->name.$table->name",
			'updating primary key',
		));
	}


	private function createIndex(Schema $schema, Table $table, Index $index): void
	{
		$c = [];
		foreach ($index->columns as $column) {
			$c[] = "\"$column\"";
		}
		$this->addQuery(new Query(
			'createIndex',
			"CREATE INDEX \"$index->name\" ON \"$schema->name\".\"$table->name\" (" . implode(", ", $c) . ")",
			"$schema->name.$table->name",
			'creating index',
			$index->name,
		));
	}


	private function removeIndex(Schema $schema, Table $table, Index $index): void
	{
		$this->addQuery(new Query(
			'dropIndex',
			"DROP INDEX \"$schema->name\".\"$index->name\"",
			"$schema->name.$table->name",
			'removing index',
			$index->name,
		));
	}


	private function updateIndex(Schema $schema, Table $table, Index $index): void
	{
		$this->removeIndex($schema, $table, $index);
		$this->createIndex($schema, $table, $index);
	}


	private function createFulltext(Schema $schema, Table $table, Column $column): void
	{
		$this->addQuery(new Query(
			'createIndex',
			"CREATE INDEX ON \"$schema->name\".\"$table->name\" USING gin(\"$column->name\")",
			"$schema->name.$table->name",
			'creating fulltext',
			$column->name,
		));
	}


	private function createColumn(Schema $schema, Table $table, Column $column): void
	{
		if ($column->type === 'fulltext') {
			$this->createFulltext($schema, $table, $column);
		}
		if ($column->auto) {
			$this->createSequence($schema, $table);
			$this->alterSequence($schema, $table, $column);
		}
		$this->addQuery(new Query(
			'alterTable',
			"ALTER TABLE \"$schema->name\".\"$table->name\" ADD COLUMN " . $this->column($schema, $table, $column),
			"$schema->name.$table->name",
			'creating column',
			$column->name,
		));
	}


	private function removeColumn(Schema $schema, Table $table, Column $column): void
	{
		$this->addQuery(new Query(
			'alterTableDrop',
			"ALTER TABLE \"$schema->name\".\"$table->name\" DROP COLUMN \"$column->name\"",
			"$schema->name.$table->name",
			'removing column',
			$column->name,
		));
	}


	private function updateColumn(Schema $schema, Table $table, Column $column): void
	{
		if ($column->auto) {
			$this->createSequence($schema, $table);
			$this->alterSequence($schema, $table, $column);
		}
		$this->addQuery(new Query(
			'alterTable',
			"ALTER TABLE \"$schema->name\".\"$table->name\" " . $this->alterColumn($table, $column),
			"$schema->name.$table->name",
			'updating column',
			$column->name,
		));
	}


	private function createUnique(Schema $schema, Table $table, Unique $unique): void
	{
		$this->addQuery(new Query(
			'alterTableAdd',
			"ALTER TABLE \"$schema->name\".\"$table->name\" ADD CONSTRAINT \"$unique->name\" " . $this->unique($unique),
			"$schema->name.$table->name",
			'creating unique key',
			$unique->name,
		));
	}


	private function removeUnique(Schema $schema, Table $table, Unique $unique): void
	{
		$this->addQuery(new Query(
			'alterTableDrop',
			"ALTER TABLE \"$schema->name\".\"$table->name\" DROP CONSTRAINT \"$unique->name\"",
			"$schema->name.$table->name",
			'removing unique key',
			$unique->name
		));
	}


	private function updateUnique(Schema $schema, Table $table, Unique $unique): void
	{
		$this->removeUnique($schema, $table, $unique);
		$this->createUnique($schema, $table, $unique);
	}


	private function createForeign(Schema $schema, Table $table, Foreign $foreignKey): void
	{
		$this->addQuery(new Query(
			'alterTableAdd',
			"ALTER TABLE \"$schema->name\".\"$table->name\" ADD CONSTRAINT " . $this->foreign($foreignKey),
			"$schema->name.$table->name",
			'creating foreign key',
			$foreignKey->name,
		));
	}


	private function removeForeign(Schema $schema, Table $table, Foreign $foreignKey): void
	{
		$this->addQuery(new Query(
			'alterTableDrop',
			"ALTER TABLE \"$schema->name\".\"$table->name\" DROP CONSTRAINT \"$foreignKey->name\"",
			"$schema->name.$table->name",
			'removing foreign key',
			$foreignKey->name
		));
	}


	private function updateForeign(Schema $schema, Table $table, Foreign $foreignKey): void
	{
		$this->removeForeign($schema, $table, $foreignKey);
		$this->createForeign($schema, $table, $foreignKey);
	}


	private function column(Schema $schema, Table $table, Column $column): string
	{
		$c = [];
		$c['name'] = "\"$column->name\"";
		$c['type'] = $this->getType($column->type);
		if (!$column->null) {
			$c['null'] = 'NOT NULL';
		}
		if ($column->default !== null) {
			$c['default'] = "DEFAULT " . $this->getDefault($column->default, $column->type);
		}
		if ($column->auto) {
			$this->createSequence($schema, $table);
			$this->alterSequence($schema, $table, $column);
			$c['auto'] = "DEFAULT nextval('{$table->name}_id_seq'::regclass)";
		}
		return implode(' ', $c);
	}


	private function alterColumn(Table $table, Column $column): string
	{
		$c = [];
		$c['type'] = "ALTER COLUMN \"$column->name\" TYPE " . $this->getType($column->type);
		if (!$column->null) {
			$c['null'] = "ALTER COLUMN \"$column->name\" SET NOT NULL";
		} else {
			$c['null'] = "ALTER COLUMN \"$column->name\" DROP NOT NULL";
		}
		if ($column->default !== null) {
			$c['default'] = "ALTER COLUMN \"$column->name\" SET DEFAULT " . $this->getDefault($column->default, $column->type);
		} else {
			$c['default'] = "ALTER COLUMN \"$column->name\" DROP DEFAULT";
		}
		if ($column->auto) {
			$c['auto'] = "ALTER COLUMN \"$column->name\" SET DEFAULT nextval('{$table->name}_id_seq'::regclass)";
		}
		return implode(', ', $c);
	}


	private function primary(Primary $key): string
	{
		$c = [];
		foreach ($key->columns as $column) {
			$c[] = "\"$column\"";
		}
		return "PRIMARY KEY (" . implode(", ", $c) . ")";
	}


	private function unique(Unique $key): string
	{
		$c = [];
		foreach ($key->columns as $column) {
			$c[] = "\"$column\"";
		}
		return "UNIQUE (" . implode(", ", $c) . ")";
	}


	private function foreign(Foreign $foreignKey): string
	{
		$foreignSchema = $foreignKey->schema ?: $this->defaultSchema;
		$k = [];
		$k['name'] = "\"{$foreignKey->name}\"";
		$k['foreignKey'] = "FOREIGN KEY (\"$foreignKey->keyColumn\")";
		$k['references'] = "REFERENCES \"$foreignSchema\".\"$foreignKey->table\" (\"$foreignKey->column\")";
		$k['onDelete'] = "ON DELETE " . strtoupper($foreignKey->onDelete);
		$k['onUpdate'] = "ON UPDATE " . strtoupper($foreignKey->onUpdate);
		return implode(' ', $k);
	}


	private function getType(string $type): string
	{
		return match($type) {
			'bool' => 'bool',
			'int' => 'int4',
			'bigint' => 'int8',
			'string' => 'varchar',
			'text' => 'text',
			'datetime' => 'timestamp',
			'float' => 'numeric',
			'fulltext' => 'tsvector',
		};
	}


	private function getDefault(mixed $default, string $type): mixed
	{
		return match($default) {
			'now' => "now()",
			default => match($type) {
				'bool' => match($default) {
					true => 'true',
					false => 'false',
					default => $default,
				},
				'int' => $default,
				'bigint' => $default,
				'string' => "'$default'",
				'text' => "'$default'",
				'datetime' => "$default",
			}
		};
	}


	private function reset(): void
	{
		foreach ($this->steps as $step => $queries) {
			$this->steps[$step] = [];
		}
	}
}
