<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition;

use Stepapo\Model\Definition\Config\Definition;


interface Analyzer
{
	function getDefinition(array $schemas): Definition;
}
