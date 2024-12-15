<?php

declare(strict_types=1);

namespace Stepapo\Model\Manipulation\Config;

use Stepapo\Utils\Config;


class ManipulationList extends Config
{
	/** @var Manipulation[][][] */ public array $manipulations = [];
}