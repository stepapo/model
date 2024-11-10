<?php

namespace Stepapo\Model\Definition;

use Stepapo\Model\Definition\Config\Definition;
use Stepapo\Utils\Service;


class Comparator implements Service
{
	public function compare(Definition $new, Definition $old): array
	{
		$result = [];
		foreach ($new->schemas as $schema) {
			if (!isset($old->schemas[$schema->name])) {
				$result['create'][$schema->name]['all'] = true;
				continue;
			}
			foreach ($schema->tables as $table) {
				if (!isset($old->schemas[$schema->name]->tables[$table->name])) {
					$result['create'][$schema->name][$table->name]['all'] = true;
					continue;
				}
				foreach ($table->columns as $column) {
					if (!isset($old->schemas[$schema->name]->tables[$table->name]->columns[$column->name])) {
						$result['create'][$schema->name][$table->name]['columns'][] = $column->name;
					} elseif (!$column->isSameAs($old->schemas[$schema->name]->tables[$table->name]->columns[$column->name])) {
						$result['update'][$schema->name][$table->name]['columns'][] = $column->name;
					}
				}
				foreach ($table->foreignKeys as $foreignKey) {
					$foreignKey->reverseName = null;
					$foreignKey->reverseOrder = null;
					if (!isset($old->schemas[$schema->name]->tables[$table->name]->foreignKeys[$foreignKey->name])) {
						$result['create'][$schema->name][$table->name]['foreignKeys'][] = $foreignKey->keyColumn;
					} elseif (!$foreignKey->isSameAs($old->schemas[$schema->name]->tables[$table->name]->foreignKeys[$foreignKey->name])) {
						$result['update'][$schema->name][$table->name]['foreignKeys'][] = $foreignKey->keyColumn;
					}
				}
				foreach ($table->indexes as $index) {
					if (!isset($old->schemas[$schema->name]->tables[$table->name]->indexes[$index->name])) {
						$result['create'][$schema->name][$table->name]['indexes'][] = $index->name;
					} elseif (!$index->isSameAs($old->schemas[$schema->name]->tables[$table->name]->indexes[$index->name])) {
						$result['update'][$schema->name][$table->name]['indexes'][] = $index->name;
					}
				}
				foreach ($table->uniqueKeys as $uniqueKey) {
					if (!isset($old->schemas[$schema->name]->tables[$table->name]->uniqueKeys[$uniqueKey->name])) {
						$result['create'][$schema->name][$table->name]['uniqueKeys'][] = $uniqueKey->name;
					} elseif (!$uniqueKey->isSameAs($old->schemas[$schema->name]->tables[$table->name]->uniqueKeys[$uniqueKey->name])) {
						$result['update'][$schema->name][$table->name]['uniqueKeys'][] = $uniqueKey->name;
					}
				}
				if (!$table->primaryKey->isSameAs($old->schemas[$schema->name]->tables[$table->name]->primaryKey)) {
					$result['update'][$schema->name][$table->name]['primaryKey'] = true;
				}
			}
		}
		foreach ($old->schemas as $schema) {
			if (!isset($new->schemas[$schema->name])) {
				$result['remove'][$schema->name]['all'] = true;
				continue;
			}
			foreach ($schema->tables as $table) {
				if (!isset($new->schemas[$schema->name]->tables[$table->name])) {
					$result['remove'][$schema->name][$table->name]['all'] = true;
					continue;
				}
				foreach ($table->columns as $column) {
					if (!isset($new->schemas[$schema->name]->tables[$table->name]->columns[$column->name])) {
						$result['remove'][$schema->name][$table->name]['columns'][] = $column->name;
					}
				}
				foreach ($table->foreignKeys as $foreignKey) {
					if (!isset($new->schemas[$schema->name]->tables[$table->name]->foreignKeys[$foreignKey->keyColumn])) {
						$result['remove'][$schema->name][$table->name]['foreignKeys'][] = $foreignKey->name;
					}
				}
				foreach ($table->indexes as $index) {
					if (!isset($new->schemas[$schema->name]->tables[$table->name]->indexes[$index->name])) {
						$result['remove'][$schema->name][$table->name]['indexes'][] = $index->name;
					}
				}
				foreach ($table->uniqueKeys as $uniqueKey) {
					if (!isset($new->schemas[$schema->name]->tables[$table->name]->uniqueKeys[$uniqueKey->name])) {
						$result['remove'][$schema->name][$table->name]['uniqueKeys'][] = $uniqueKey->name;
					}
				}
			}
		}
		return $result;
	}
}