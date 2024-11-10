<?php

declare(strict_types=1);

namespace Stepapo\Model;


abstract class MigrationGroup
{
	public function __construct(
		public string $name,
		public string $class,
		public array $dependencies = [],
	) {}
}