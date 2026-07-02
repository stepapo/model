<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm;

use Stepapo\Model\Data\Item;


interface PostProcessable
{
	function postProcessFromData(Item $data, IStepapoEntity $entity, bool $skipDefaults = false): IStepapoEntity;
}
