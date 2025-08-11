<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition;

use Nextras\Dbal\Connection;
use Nextras\Dbal\Result\Row;
use Stepapo\Model\Definition\Config\Column;
use Stepapo\Model\Definition\Config\Definition;
use Stepapo\Model\Definition\Config\Foreign;
use Stepapo\Model\Definition\Config\Index;
use Stepapo\Model\Definition\Config\Primary;
use Stepapo\Model\Definition\Config\Schema;
use Stepapo\Model\Definition\Config\Table;
use Stepapo\Model\Definition\Config\Unique;


class MysqlAnalyzer implements Analyzer
{
	public function __construct(
		private Connection $dbal,
	) {}


	public function getDefinition(array $schemas = ['public']): Definition
	{
		$definition = new Definition;
		foreach ($schemas as $s) {
			$schema = new Schema;
			$schema->name = $s;
			$definition->schemas[$s] = $schema;
			foreach ($this->getTables($s) as $t) {
				if ($t->name === 'migrations') {
					continue;
				}
				$table = new Table;
				$table->name = $t->name;
				$schema->tables[$t->name] = $table;
				foreach ($this->getColumns($s, $t->name) as $c) {
					$column = new Column;
					$column->name = $c->name;
					$type = $this->getType($c->type);
					$column->type = $type;
					$column->default = $c->is_primary ? null : $this->getDefault($c->default, $type);
					$column->null = $c->is_nullable === 1;
					$column->auto = $c->is_autoincrement === 1;
					$table->columns[$c->name] = $column;
				}
				foreach ($this->getForeignKeys($s, $t->name) as $f) {
					$foreignKey = new Foreign;
					$foreignKey->name = $f->name;
					$foreignKey->keyColumn = $f->column;
					$foreignKey->schema = $f->ref_table_schema;
					$foreignKey->table = $f->ref_table;
					$foreignKey->column = $f->ref_column;
					$foreignKey->onUpdate = $this->getRestriction($f->on_update);
					$foreignKey->onDelete = $this->getRestriction($f->on_delete);
					$table->foreignKeys[$f->name] = $foreignKey;
				}
				foreach ($this->getUniqueKeys($s, $t->name) as $u) {
					$unique = new Unique;
					$unique->name = $u->name;
					$unique->columns = $this->getKeyColumns($u->name, $s, $t->name);
					$table->uniqueKeys[$u->name] = $unique;
				}
				$p = $this->getPrimaryKey($s, $t->name);
				foreach ($this->getIndexesWithColumns($s, $t->name) as $i) {
					if (isset($table->indexes[$i->name])) {
						$table->indexes[$i->name]->columns[] = $i->column;
					} else {
						if ($i->type === 'FULLTEXT') {
							$table->columns[$i->column]->type = 'fulltext';
						} else {
							$index = new Index;
							$index->name = $i->name;
							$index->columns = [$i->column];
							$table->indexes[$i->name] = $index;
						}
					}
				}
				$primary = new Primary;
				$primary->columns = $this->getKeyColumns($p->name, $s, $t->name);
				$table->primaryKey = $primary;
			}
		}
		return $definition;
	}


	private function getTables(string $schema): array
	{
		return $this->dbal->query("
			SELECT 
				TABLE_NAME AS name
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = %s 
			AND TABLE_TYPE = 'BASE TABLE'
			ORDER BY TABLE_NAME
		", $schema)->fetchAll();
	}


	private function getColumns(string $schema, string $table): array
	{
		return $this->dbal->query("
			SELECT 
				COLUMN_NAME AS name,
				DATA_TYPE AS `type`,
				COLUMN_DEFAULT as `default`,
				IF(COLUMN_KEY='PRI',1,0) AS is_primary,
				IF(EXTRA='auto_increment',1,0) AS is_autoincrement,
				IF(IS_NULLABLE='YES',1,0) AS is_nullable
			FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = %s
			AND TABLE_NAME = %s
			ORDER BY COLUMN_NAME
		", $schema, $table)->fetchAll();
	}


	private function getUniqueKeys(string $schema, string $table): array
	{
		return $this->dbal->query("
			SELECT
				CONSTRAINT_NAME as name,
				TABLE_SCHEMA as `schema`,
				TABLE_NAME as `table`
			FROM information_schema.TABLE_CONSTRAINTS
			WHERE TABLE_SCHEMA = %s
			AND TABLE_NAME = %s
			AND CONSTRAINT_TYPE = 'UNIQUE'
			ORDER BY CONSTRAINT_NAME
		", $schema, $table)->fetchAll();
	}


	private function getPrimaryKey(string $schema, string $table): Row
	{
		return $this->dbal->query("
			SELECT
				CONSTRAINT_NAME as name,
				TABLE_SCHEMA as `schema`,
				TABLE_NAME as `table`
			FROM information_schema.TABLE_CONSTRAINTS
			WHERE TABLE_SCHEMA = %s
			AND TABLE_NAME = %s
			AND CONSTRAINT_TYPE = 'PRIMARY KEY'
		", $schema, $table)->fetch();
	}


	private function getForeignKeys(string $schema, string $table): array
	{
		return $this->dbal->query("
			SELECT
				REFERENTIAL_CONSTRAINTS.CONSTRAINT_NAME AS name,
				KEY_COLUMN_USAGE.TABLE_SCHEMA AS `schema`,
				KEY_COLUMN_USAGE.TABLE_NAME AS `table`,
				KEY_COLUMN_USAGE.COLUMN_NAME AS `column`,
				KEY_COLUMN_USAGE.REFERENCED_TABLE_SCHEMA AS ref_table_schema,
				KEY_COLUMN_USAGE.REFERENCED_TABLE_NAME AS ref_table,
				KEY_COLUMN_USAGE.REFERENCED_COLUMN_NAME AS ref_column,
				REFERENTIAL_CONSTRAINTS.UPDATE_RULE AS on_update,
				REFERENTIAL_CONSTRAINTS.DELETE_RULE AS on_delete
			FROM information_schema.REFERENTIAL_CONSTRAINTS
			JOIN information_schema.KEY_COLUMN_USAGE USING (CONSTRAINT_SCHEMA, CONSTRAINT_NAME)
			WHERE KEY_COLUMN_USAGE.TABLE_SCHEMA = %s
			AND KEY_COLUMN_USAGE.TABLE_NAME = %s
			AND KEY_COLUMN_USAGE.ORDINAL_POSITION = 1
			ORDER BY REFERENTIAL_CONSTRAINTS.CONSTRAINT_NAME
		", $schema, $table)->fetchAll();
	}


	private function getIndexesWithColumns(string $schema, string $table): array
	{
		return $this->dbal->query("
			SELECT 
				INDEX_NAME AS name,
				TABLE_SCHEMA AS `schema`,
				TABLE_NAME AS `table`,
				COLUMN_NAME AS `column`,
				INDEX_TYPE AS `type`
			FROM information_schema.STATISTICS
			WHERE TABLE_SCHEMA = %s
			AND TABLE_NAME = %s
			AND NON_UNIQUE = 1
			ORDER BY INDEX_NAME
		", $schema, $table)->fetchAll();
	}


	private function getKeyColumns(string $key, string $schema, string $table): array
	{
		return $this->dbal->query("
			SELECT COLUMN_NAME AS `column`
			FROM information_schema.KEY_COLUMN_USAGE
			WHERE CONSTRAINT_NAME = %s
			AND TABLE_SCHEMA = %s
			AND TABLE_NAME = %s
			ORDER BY COLUMN_NAME
		", $key, $schema, $table)->fetchPairs(null, 'column');
	}


	private function getDefault(mixed $default, string $type): mixed
	{
		if ($default) {
			preg_match("/\'(.*)\'/", $default, $m);
			$default = $m[1] ?? $default;
		}
		return match($default) {
			'current_timestamp()' => "now",
			default => match($type) {
				'bool' => match ($default) {
					'1' => true,
					'0' => false,
					default => null,
				},
				'int' => $default === 'NULL' || $default === null ? null : (int) $default,
				'bigint' => $default === 'NULL' || $default === null ? null : (int) $default,
				'float' => $default === 'NULL' || $default === null ? null : (int) $default,
				default => $default === 'NULL' ? null : $default,
			}
		};
	}


	private function getType(string $type): string
	{
		return match($type) {
			'tinyint' => 'bool',
			'int' => 'int',
			'bigint' => 'bigint',
			'varchar' => 'string',
			'text' => 'text',
			'timestamp' => 'datetime',
			'float' => 'float',
		};
	}


	private function getRestriction(string $restriction): string
	{
		return str_replace('no action', 'restrict', strtolower($restriction));
	}
}