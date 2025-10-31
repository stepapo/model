<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition\Config;

use Nette\Utils\Strings;
use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Config;


class Definition extends Config
{
	/** @var Schema[]|array */ #[ArrayOfType(Schema::class)] public array $schemas;

	
	public function __toString(): string
	{
		$result = "schemas:\n";
		foreach ($this->schemas as $schema) {
			$result .= Strings::indent("$schema", 1);
		}
		return $result;
	}
}