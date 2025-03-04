<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition;

use Stepapo\Utils\Service;


interface HasDefinitionGroup extends Service
{
	public function getDefinitionGroup(): DefinitionGroup;
}