<?php

declare(strict_types=1);

namespace Stepapo\Model\Manipulation;

use Stepapo\Utils\Service;


interface HasManipulationGroups extends Service
{
	/** @return ManipulationGroup[] */
	public function getManipulationGroups(): array;
}