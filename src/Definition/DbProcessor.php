<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition;


interface DbProcessor
{
	function process(array $folders): int;
}
