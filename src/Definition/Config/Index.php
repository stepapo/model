<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition\Config;

use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Attribute\ToArray;
use Stepapo\Utils\Attribute\ValueProperty;
use Stepapo\Utils\Schematic;


class Index extends Schematic
{
	#[KeyProperty] public string|int|null $name = null;
	/** @var string[] */ #[ValueProperty, ToArray] public array $columns;


	public function process(string $tableName)
	{
		if (!$this->name || !is_string($this->name)) {
			sort($this->columns);
			$this->name = $tableName . '_' . implode('_', $this->columns) . '_ix';
		}
	}
}