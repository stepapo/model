<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition\Config;

use Nextras\Orm\StorageReflection\StringHelper;
use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Schematic;


class Column extends Schematic
{
	#[KeyProperty] public string $name;
	public string $type;
	public bool $null;
	public bool $auto = false;
	public mixed $default = null;
	public ?string $onUpdate = null;


	public function getPhpName(?Foreign $foreign = null): string
	{
		return StringHelper::camelize($foreign ? str_replace('_id', '', $this->name) : $this->name);
	}


	public function getPhpType(?Foreign $foreign = null): string
	{
		return ($foreign
			? $foreign->getPhpTable()
			: match($this->type) {
				'bool' => 'bool',
				'int' => 'int',
				'bigint' => 'int',
				'string' => 'string',
				'text' => 'string',
				'datetime' => 'DateTimeImmutable',
				'float' => 'float',
				'fulltext' => 'string',
			}) . ($this->null ? '|null' : '');
	}


	public function getPhpDefault(): ?string
	{
		return match($this->default) {
			null => null,
			'now' => 'now',
			default => match($this->type) {
				'bool' => match($this->default) {
					true => 'true',
					false => 'false',
					default => $this->default,
				},
				'int' => (string) $this->default,
				'bigint' => (string) $this->default,
				'float' => (string) $this->default,
				default => "'$this->default'",
			}
		};
	}
}