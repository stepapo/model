<?php

declare(strict_types=1);

namespace Stepapo\Model\Manipulation\Config;

use Stepapo\Utils\Schematic;
use Webovac\Core\Definition\Manipulation;


class ManipulationList extends Schematic
{
	/** @var Manipulation[][][] */ public array $manipulations = [];
}