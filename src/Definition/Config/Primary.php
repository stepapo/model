<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition\Config;

use Stepapo\Utils\Attribute\ToArray;
use Stepapo\Utils\Attribute\ValueProperty;
use Stepapo\Utils\Config;


class Primary extends Config
{
	/** @var string[] */ #[ValueProperty, ToArray] public array $columns;
}