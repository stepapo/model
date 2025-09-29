<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition;

use Stepapo\Model\Definition\Config\Definition;
use Stepapo\Utils\Service;


class Comparator implements Service
{
	public function compare(Definition $new, Definition $old): array
	{
		foreach ($new->schemas as $schema) {
			foreach ($schema->tables as $table) {
				foreach ($table->foreignKeys as $foreignKey) {
					if ($foreignKey->schema === null) {
						$foreignKey->schema = $schema->name;
					}
				}
			}
		}
		$result = [];
		foreach ($new->schemas as $schema) {
			if (!isset($old->schemas[$schema->name])) {
				$result['create'][$schema->name]['all'] = true;
				continue;
			}
			$oldSchema = $old->schemas[$schema->name];
			foreach ($schema->tables as $table) {
				if (!isset($old->schemas[$schema->name]->tables[$table->name])) {
					$result['create'][$schema->name][$table->name]['all'] = true;
					continue;
				}
				$oldTable = $oldSchema->tables[$table->name];
				foreach ($table->columns as $column) {
					if (!isset($oldTable->columns[$column->name])) {
						$result['create'][$schema->name][$table->name]['columns'][] = $column->name;
					} elseif (!$column->isSameAs($oldTable->columns[$column->name])) {
						$result['update'][$schema->name][$table->name]['columns'][] = $column->name;
					}
				}
				foreach ($table->foreignKeys as $foreignKey) {
					$foreignKey->reverseName = null;
					$foreignKey->reverseOrder = null;
					if (!isset($oldTable->foreignKeys[$foreignKey->name])) {
						$result['create'][$schema->name][$table->name]['foreignKeys'][] = $foreignKey->keyColumn;
					} elseif (!$foreignKey->isSameAs($oldTable->foreignKeys[$foreignKey->name])) {
						$result['update'][$schema->name][$table->name]['foreignKeys'][] = $foreignKey->keyColumn;
					}
				}
				foreach ($table->indexes as $index) {
					if (!isset($oldTable->indexes[$index->name])) {
						$result['create'][$schema->name][$table->name]['indexes'][] = $index->name;
					} elseif (!$index->isSameAs($oldTable->indexes[$index->name])) {
						$result['update'][$schema->name][$table->name]['indexes'][] = $index->name;
					}
				}
				foreach ($table->uniqueKeys as $uniqueKey) {
					if (!isset($oldTable->uniqueKeys[$uniqueKey->name])) {
						$result['create'][$schema->name][$table->name]['uniqueKeys'][] = $uniqueKey->name;
					} elseif (!$uniqueKey->isSameAs($oldTable->uniqueKeys[$uniqueKey->name])) {
						$result['update'][$schema->name][$table->name]['uniqueKeys'][] = $uniqueKey->name;
					}
				}
				if (!$table->primaryKey->isSameAs($oldTable->primaryKey)) {
					$result['update'][$schema->name][$table->name]['primaryKey'] = true;
				}
			}
		}
		foreach ($old->schemas as $schema) {
			if (!isset($new->schemas[$schema->name])) {
				$result['remove'][$schema->name]['all'] = true;
				continue;
			}
			$newSchema = $new->schemas[$schema->name];
			foreach ($schema->tables as $table) {
				if (!isset($new->schemas[$schema->name]->tables[$table->name])) {
					$result['remove'][$schema->name][$table->name]['all'] = true;
					continue;
				}
				$newTable = $newSchema->tables[$table->name];
				foreach ($table->columns as $column) {
					if (!isset($newTable->columns[$column->name])) {
						$result['remove'][$schema->name][$table->name]['columns'][] = $column->name;
					}
				}
				foreach ($table->foreignKeys as $foreignKey) {
					if (!isset($newTable->foreignKeys[$foreignKey->keyColumn])) {
						$result['remove'][$schema->name][$table->name]['foreignKeys'][] = $foreignKey->name;
					}
				}
				foreach ($table->indexes as $index) {
					if (!isset($newTable->indexes[$index->name])) {
						$result['remove'][$schema->name][$table->name]['indexes'][] = $index->name;
					}
				}
				foreach ($table->uniqueKeys as $uniqueKey) {
					if (!isset($newTable->uniqueKeys[$uniqueKey->name])) {
						$result['remove'][$schema->name][$table->name]['uniqueKeys'][] = $uniqueKey->name;
					}
				}
			}
		}
		return $result;
	}
}