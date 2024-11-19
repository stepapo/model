<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition\Config;

use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Schematic;


class Definition extends Schematic
{
	/** @var Schema[]|array */ #[ArrayOfType(Schema::class)] public array $schemas;
}