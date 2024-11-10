<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition;


class Query
{
	public function __construct(
		public string $step,
		public string $sql,
		public ?string $table = null,
		public ?string $text = null,
		public ?string $item = null,
	) {}
}