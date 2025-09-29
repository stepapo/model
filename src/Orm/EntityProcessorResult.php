<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm;


class EntityProcessorResult
{
	public function __construct(
		public StepapoEntity $entity,
		public bool $isModified = false,
		public ?DiffList $modifiedValues = null,
	) {}
}