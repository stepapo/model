<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition\Config;

use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Schematic;


class Schema extends Schematic
{
	#[KeyProperty] public string $name;
	/** @var Table[]|array */ #[ArrayOfType(Table::class)] public array $tables = [];
}