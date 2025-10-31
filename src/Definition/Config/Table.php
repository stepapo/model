<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition\Config;

use Nette\Utils\Strings;
use Nextras\Orm\StorageReflection\StringHelper;
use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Attribute\SkipInComparison;
use Stepapo\Utils\Attribute\Type;
use Stepapo\Utils\Config;


class Table extends Config
{
	#[KeyProperty] public string $name;
	/** @var Column[] */ #[ArrayOfType(Column::class)] public array $columns = [];
	#[Type(Primary::class)] public ?Primary $primaryKey = null;
	/** @var Unique[] */ #[ArrayOfType(Unique::class)] public array $uniqueKeys = [];
	/** @var Index[] */ #[ArrayOfType(Index::class)] public array $indexes = [];
	/** @var Foreign[] */ #[ArrayOfType(Foreign::class)] public array $foreignKeys = [];
	#[SkipInComparison] public ?string $entity = null;


	public function getPhpName()
	{

		return $this->entity ?: ucfirst(StringHelper::camelize($this->name));
	}


	public function __toString(): string
	{
		$result = "$this->name:\n";
		if ($this->columns) {
			$result .= Strings::indent("columns:\n", 1);
			foreach ($this->columns as $column) {
				$result .= Strings::indent("$column\n", 2);
			}
		}
		if ($this->primaryKey) {
			$result .= Strings::indent("primaryKey: $this->primaryKey\n", 1);
		}
		if ($this->uniqueKeys) {
			$result .= Strings::indent("uniqueKeys: [" . implode(', ', $this->uniqueKeys) . "]\n", 1);
		}
		if ($this->indexes) {
			$result .= Strings::indent("indexes: [" . implode(', ', $this->indexes) . "]\n", 1);
		}
		if ($this->foreignKeys) {
			$result .= Strings::indent("foreignKeys:\n", 1);
			foreach ($this->foreignKeys as $foreignKey) {
				$result .= Strings::indent("$foreignKey\n", 2);
			}
		}
		return $result;
	}
}