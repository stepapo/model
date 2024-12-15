<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition\Config;

use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Config;


class Schema extends Config
{
	#[KeyProperty] public string $name;
	/** @var Table[]|array */ #[ArrayOfType(Table::class)] public array $tables = [];
}