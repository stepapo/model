<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition\Config;

use Nette\Utils\Strings;
use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Config;


class Schema extends Config
{
	#[KeyProperty] public string $name;
	/** @var Table[]|array */ #[ArrayOfType(Table::class)] public array $tables = [];


	public function __toString(): string
	{
		$result = "$this->name:\n";
		$result .= Strings::indent("tables:\n", 1);
		foreach ($this->tables as $table) {
			$result .= Strings::indent("$table", 2);
		}
		return $result;
	}
}