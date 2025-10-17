<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition\Config;

use Nextras\Orm\StorageReflection\StringHelper;
use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Attribute\SkipInComparison;
use Stepapo\Utils\Config;


class Foreign extends Config
{
	public ?string $name = null;
	#[KeyProperty] public string $keyColumn;
	public ?string $schema = null;
	public string $table;
	public string $column;
	public string $onDelete = 'cascade';
	public string $onUpdate = 'cascade';
	public ?string $reverseName = null;
	public ?string $reverseOrder = null;
	#[SkipInComparison] public bool $reverseSkipInManipulation = false;
	#[SkipInComparison] public bool $reverseDontCache = false;


	public function process(string $tableName)
	{
		if (!$this->name || !is_string($this->name)) {
			$this->name = $tableName . '_' . $this->keyColumn . '_fk';
		}
	}


	public function getPhpTable(): string
	{
		return ucfirst(StringHelper::camelize($this->table));
	}


	public function getPhpSchema(): ?string
	{
		return $this->schema ? ucfirst(StringHelper::camelize($this->schema)) : null;
	}
}