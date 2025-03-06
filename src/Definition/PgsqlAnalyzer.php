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


class PgsqlAnalyzer implements Analyzer
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
				foreach ($this->getColumns((int) $t->id) as $c) {
					$column = new Column;
					$column->name = $c->name;
					$type = $this->getType($c->type);
					$column->type = $type;
					$column->default = $c->is_primary ? null : $this->getDefault($c->default, $type);
					$column->null = $c->is_nullable;
					$column->auto = $c->is_autoincrement;
					$table->columns[$c->name] = $column;
				}
				foreach ($this->getForeignKeys((int) $t->id) as $f) {
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
				foreach ($this->getUniqueKeys((int) $t->id) as $u) {
					$unique = new Unique;
					$unique->name = $u->name;
					$unique->columns = $this->getKeyColumns((int) $u->id);
					$table->uniqueKeys[$u->name] = $unique;
				}
				$p = $this->getPrimaryKey((int) $t->id);
				foreach ($this->getIndexes((int) $t->id) as $i) {
					$index = new Index;
					$index->name = $i->name;
					$index->columns = $this->getKeyColumns((int) $i->id);
					$table->indexes[$i->name] = $index;
				}
				$primary = new Primary;
				$primary->columns = $this->getKeyColumns((int) $p->id);
				$table->primaryKey = $primary;
			}
		}
		return $definition;
	}


	private function getTables(string $schema): array
	{
//		return $this->dbal->query("
//			SELECT
//				TABLE_SCHEMA,
//				TABLE_NAME,
//				TABLE_TYPE
//			FROM information_schema.TABLES
//			-- SCHEMA
//			WHERE TABLE_SCHEMA = %s;
//		", 'public')->fetchAll();
		return $this->dbal->query("
			SELECT DISTINCT ON (pg_class.relname)
				pg_class.oid AS id,
				pg_class.relname::varchar AS name,
				pg_namespace.nspname::varchar AS schema
			FROM pg_catalog.pg_class
			JOIN pg_catalog.pg_namespace ON pg_namespace.oid = pg_class.relnamespace
			WHERE pg_class.relkind = 'r'
			-- SCHEMA
			AND pg_namespace.nspname IN (%s)
			ORDER BY pg_class.relname
		", $schema)->fetchAll();
	}


	private function getColumns(int $id): array
	{
		return $this->dbal->query("
			SELECT
			  pg_attribute.attname::varchar AS name,
			  UPPER(pg_type.typname) AS type,
			  CASE WHEN pg_attribute.atttypmod = -1 THEN NULL ELSE pg_attribute.atttypmod -4 END AS size,
			  pg_catalog.pg_get_expr(pg_attrdef.adbin, 'pg_catalog.pg_attrdef'::regclass)::varchar AS default,
			  COALESCE(pg_constraint.contype = 'p', FALSE) AS is_primary,
			  COALESCE(pg_constraint.contype = 'p' AND strpos(pg_get_expr(pg_attrdef.adbin, pg_attrdef.adrelid), 'nextval') = 1, FALSE) AS is_autoincrement,
			  NOT (pg_attribute.attnotnull OR pg_type.typtype = 'd' AND pg_type.typnotnull) AS is_nullable
			FROM pg_catalog.pg_attribute
			JOIN pg_catalog.pg_class ON pg_attribute.attrelid = pg_class.oid
			JOIN pg_catalog.pg_type ON pg_attribute.atttypid = pg_type.oid
			LEFT JOIN pg_catalog.pg_attrdef ON pg_attrdef.adrelid = pg_class.oid AND pg_attrdef.adnum = pg_attribute.attnum
			LEFT JOIN pg_catalog.pg_constraint ON pg_constraint.connamespace = pg_class.relnamespace AND contype = 'p' AND pg_constraint.conrelid = pg_class.oid AND pg_attribute.attnum = ANY(pg_constraint.conkey)
			WHERE pg_class.relkind IN ('r')
			-- TABLE ID:
			AND pg_class.oid = %i
			AND pg_attribute.attnum > 0
			AND NOT pg_attribute.attisdropped
			ORDER BY pg_attribute.attnum
		", $id)->fetchAll();
	}


	private function getUniqueKeys(int $id): array
	{
		return $this->dbal->query("
			SELECT
				pg_constraint.conindid AS id,
				pg_constraint.conname::varchar AS name
			FROM pg_catalog.pg_constraint
			WHERE pg_constraint.contype = 'u'
			AND pg_constraint.conrelid = %i
		", $id)->fetchAll();
	}


	private function getPrimaryKey(int $id): Row
	{
		return $this->dbal->query("
			SELECT
				pg_constraint.conindid AS id,
				pg_constraint.conname::varchar AS name
			FROM pg_catalog.pg_constraint
			WHERE pg_constraint.contype = 'p'
			AND pg_constraint.conrelid = %i
		", $id)->fetch();
	}


	private function getForeignKeys(int $id): array
	{
		return $this->dbal->query("
			SELECT
				pg_constraint.conname::varchar AS name,
				pg_namespace.nspname::varchar AS schema,
				pg_attribute.attname::varchar AS column,
				pg_class_foreign.relname::varchar AS ref_table,
				pg_namespace_foreign.nspname::varchar AS ref_table_schema,
				pg_attribute_foreign.attname::varchar AS ref_column,
				pg_constraint.confupdtype::varchar AS on_update,
				pg_constraint.confdeltype::varchar AS on_delete
			FROM pg_catalog.pg_constraint
			JOIN pg_catalog.pg_class ON pg_constraint.conrelid = pg_class.oid
			JOIN pg_catalog.pg_class AS pg_class_foreign ON pg_constraint.confrelid = pg_class_foreign.oid
			JOIN pg_catalog.pg_namespace ON pg_namespace.oid = pg_class.relnamespace
			JOIN pg_catalog.pg_namespace AS pg_namespace_foreign ON pg_namespace_foreign.oid = pg_class_foreign.relnamespace
			JOIN pg_catalog.pg_attribute ON pg_attribute.attrelid = pg_class.oid AND pg_attribute.attnum = pg_constraint.conkey[1]
			JOIN pg_catalog.pg_attribute AS pg_attribute_foreign ON pg_attribute_foreign.attrelid = pg_class_foreign.oid AND pg_attribute_foreign.attnum = pg_constraint.confkey[1]
			WHERE pg_constraint.contype = 'f'
			AND pg_class.oid = %i
		", $id)->fetchAll();
	}


	private function getIndexes(int $id): array
	{
		return $this->dbal->query("
			SELECT 
				pg_index.indexrelid AS id,
				pg_index.indexrelid::regclass as name
			FROM pg_catalog.pg_index
			JOIN pg_catalog.pg_class ON pg_class.oid = pg_index.indrelid
			WHERE pg_class.oid = %i 
			AND pg_index.indisprimary = false
			AND pg_index.indisunique = false
		", $id)->fetchAll();
	}


	private function getKeyColumns(int $id): array
	{
		return $this->dbal->query("
			SELECT pg_catalog.pg_get_indexdef(pg_attribute.attrelid, pg_attribute.attnum, true) AS column
			FROM pg_catalog.pg_attribute
			-- INDEX ID:
			WHERE pg_attribute.attrelid = %i
			ORDER BY pg_attribute.attnum
		", $id)->fetchPairs(null, 'column');
	}


	private function getDefault(mixed $default, string $type): mixed
	{
		if ($default) {
			preg_match("/\'(.*)\'::character varying/", $default, $m);
			$default = $m[1] ?? $default;
		}
		return match($default) {
			'now()' => "now",
			default => match($type) {
				'bool' => match ($default) {
					'true' => true,
					'false' => false,
					default => null,
				},
				'int' => is_null($default) ? null : (int) $default,
				'bigint' => is_null($default) ? null : (int) $default,
				'float' => is_null($default) ? null : (int) $default,
				default => $default,
			}
		};
	}


	private function getType(string $type): string
	{
		$type = strtolower($type);
		return match($type) {
			'bool' => 'bool',
			'int4' => 'int',
			'int8' => 'bigint',
			'varchar' => 'string',
			'text' => 'text',
			'timestamp' => 'datetime',
			'numeric' => 'float',
			'tsvector' => 'fulltext',
		};
	}


	private function getRestriction(string $restriction): string
	{
		return match($restriction) {
			'r' => 'restrict',
			'c' => 'cascade',
			'n' => 'set null',
		};
	}
}