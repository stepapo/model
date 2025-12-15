<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition;

use Nette\InvalidArgumentException;
use Nette\Utils\FileInfo;
use Nette\Utils\Finder;
use Stepapo\Model\Definition\Config\Column;
use Stepapo\Model\Definition\Config\Definition;
use Stepapo\Model\Definition\Config\Table;
use Stepapo\Utils\Service;


class Collector implements Service
{
	public function __construct(
		private array $parameters,
	) {}


	public function getDefinition(array $folders): Definition
	{
		$files = [];
		foreach ($folders as $folder) {
			$neonFiles = Finder::findFiles("*.neon")->from($folder);
			$files = array_merge($files, $neonFiles->collect());
		}
		$definitions = [];
		/** @var FileInfo $file */
		foreach ($files as $file) {
			$definitions[] = Definition::createFromNeon($file->getPathname(), $this->parameters);
		}
		return $this->mergeDefinitions($definitions);
	}


	/** @param Definition[] $definitions */
	public function mergeDefinitions(array $definitions): Definition
	{
		foreach ($definitions as $definition) {
			if (!isset($mergedDefinition)) {
				$mergedDefinition = $definition;
				continue;
			}
			foreach ($definition->schemas as $schema) {
				if (!isset($mergedDefinition->schemas[$schema->name])) {
					$mergedDefinition->schemas[$schema->name] = $schema;
					continue;
				}
				foreach ($schema->tables as $table) {
					if (!isset($mergedDefinition->schemas[$schema->name]->tables[$table->name])) {
						$mergedDefinition->schemas[$schema->name]->tables[$table->name] = $table;
						continue;
					}
					foreach ($table->columns as $column) {
						if (isset($mergedDefinition->schemas[$schema->name]->tables[$table->name]->columns[$column->name])) {
							throw new InvalidArgumentException("Duplicate definition of column '$column->name' in table '$table->name'.");
						}
						$mergedDefinition->schemas[$schema->name]->tables[$table->name]->columns[$column->name] = $column;
					}
					foreach ($table->foreignKeys as $foreignKey) {
						if (isset($mergedDefinition->schemas[$schema->name]->tables[$table->name]->foreignKeys[$foreignKey->keyColumn])) {
							throw new InvalidArgumentException("Duplicate definition of foreign key '$foreignKey->keyColumn' in table '$table->name'.");
						}
						$mergedDefinition->schemas[$schema->name]->tables[$table->name]->foreignKeys[$foreignKey->keyColumn] = $foreignKey;
					}
					foreach ($table->uniqueKeys as $uniqueKey) {
						if (isset($mergedDefinition->schemas[$schema->name]->tables[$table->name]->uniqueKeys[$uniqueKey->name])) {
							throw new InvalidArgumentException("Duplicate definition of unique key '$uniqueKey->name' in table '$table->name'.");
						}
						$mergedDefinition->schemas[$schema->name]->tables[$table->name]->uniqueKeys[$uniqueKey->name] = $uniqueKey;
					}
					foreach ($table->indexes as $index) {
						if (isset($mergedDefinition->schemas[$schema->name]->tables[$table->name]->indexes[$index->name])) {
							throw new InvalidArgumentException("Duplicate definition of index '$index->name' in table '$table->name'.");
						}
						$mergedDefinition->schemas[$schema->name]->tables[$table->name]->indexes[$index->name] = $index;
					}
					if ($table->primaryKey) {
						if (isset($mergedDefinition->schemas[$schema->name]->tables[$table->name]->primaryKey)) {
							throw new InvalidArgumentException("Duplicate definition of primary key in table '$table->name'");
						}
						$mergedDefinition->schemas[$schema->name]->tables[$table->name]->primaryKey = $table->primaryKey;
					}
				}
			}
		}
		ksort($mergedDefinition->schemas);
		foreach ($mergedDefinition->schemas as $schema) {
			ksort($mergedDefinition->schemas[$schema->name]->tables);
			foreach ($schema->tables as $table) {
				/*$mergedDefinition->schemas[$schema->name]->tables[$table->name] = */$this->sortColumns($table);
			}
		}
		return $mergedDefinition;
	}


	private function sortColumns(Table $table): void
	{
		uasort($table->columns, function (Column $a, Column $b) use ($table) {
			if (
				[in_array($b->name, $table->primaryKey?->columns ?: [], true), array_key_exists($b->name, $table->foreignKeys), in_array($a->name, ['created_by_person_id', 'updated_by_person_id'], true), in_array($a->name, ['created_at', 'updated_at'], true), $a->type === 'datetime', $a->type === 'dateinterval']
				===
				[in_array($a->name, $table->primaryKey?->columns ?: [], true), array_key_exists($a->name, $table->foreignKeys), in_array($b->name, ['created_by_person_id', 'updated_by_person_id'], true), in_array($b->name, ['created_at', 'updated_at'], true), $b->type === 'datetime', $b->type === 'dateinterval']
			) {
				if ($a->type === $b->type) {
					return strcmp($a->name, $b->name);
				}
				return strcmp($a->type, $b->type);
			}
			return [in_array($b->name, $table->primaryKey?->columns ?: [], true), array_key_exists($b->name, $table->foreignKeys), in_array($a->name, ['created_by_person_id', 'updated_by_person_id'], true), in_array($a->name, ['created_at', 'updated_at'], true), $a->type === 'datetime', $a->type === 'dateinterval']
				<=>
				[in_array($a->name, $table->primaryKey?->columns ?: [], true), array_key_exists($a->name, $table->foreignKeys), in_array($b->name, ['created_by_person_id', 'updated_by_person_id'], true), in_array($b->name, ['created_at', 'updated_at'], true), $b->type === 'datetime', $b->type === 'dateinterval'];
		});
	}
}
