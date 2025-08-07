<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm;


class DiffList
{
	/** @var mixed[] */ public array $propertyList;
	/** @var DiffList[] */ public array $entityList;
}