<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition\Config;

use Nextras\Orm\StorageReflection\StringHelper;
use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Attribute\Type;
use Stepapo\Utils\Schematic;


class Table extends Schematic
{
	public string $type = 'create';
	#[KeyProperty] public string $name;
	public ?string $module = null;
	/** @var Column[] */ #[ArrayOfType(Column::class)] public array $columns = [];
	#[Type(Primary::class)] public ?Primary $primaryKey = null;
	/** @var Unique[] */ #[ArrayOfType(Unique::class)] public array $uniqueKeys = [];
	/** @var Index[] */ #[ArrayOfType(Index::class)] public array $indexes = [];
	/** @var Foreign[] */ #[ArrayOfType(Foreign::class)] public array $foreignKeys = [];


	public function getPhpName()
	{
		return ucfirst(StringHelper::camelize($this->name));
	}
}