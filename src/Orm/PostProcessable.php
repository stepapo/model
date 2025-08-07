<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm;

use Stepapo\Model\Data\Item;
use Webovac\Core\Model\CmsEntity;


interface PostProcessable
{
	function postProcessFromData(Item $data, CmsEntity $entity, bool $skipDefaults = false): CmsEntity;
}