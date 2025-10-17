<?php

declare(strict_types=1);

namespace Stepapo\Model\Definition\Config;

use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Orm\StorageReflection\StringHelper;
use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Attribute\SkipInComparison;
use Stepapo\Utils\Config;


class Column extends Config
{
	#[KeyProperty] public string $name;
	public string $type;
	public bool $null;
	public bool $auto = false;
	public mixed $default = null;
	public ?string $onUpdate = null;
	#[SkipInComparison] public bool $private = false;
	#[SkipInComparison] public bool $internal = false;
	#[SkipInComparison] public mixed $dataDefault = null;
	#[SkipInComparison] public bool $keyProperty = false;
	#[SkipInComparison] public bool $valueProperty = false;
	#[SkipInComparison] public bool $dontCache = false;
	#[SkipInComparison] public bool $skipInManipulation = false;
	#[SkipInComparison] public bool $showData = false;


	public function getPhpName(?Foreign $foreign = null): string
	{
		return StringHelper::camelize($foreign ? str_replace('_id', '', $this->name) : $this->name);
	}


	public function getPhpType(?Foreign $foreign = null): string
	{
		return $foreign ? $foreign->getPhpTable() : match($this->type) {
			'bool' => 'bool',
			'int' => 'int',
			'bigint' => 'int',
			'string' => 'string',
			'text' => 'string',
			'datetime' => 'DateTimeImmutable',
			'float' => 'float',
			'fulltext' => 'string',
		};
	}


	public function getNextrasType(?Foreign $foreign = null): string
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
			})
			. ($this->null ? '|null' : '')
			. ($this->private ? '|PrivateProperty' : '')
			. ($this->internal ? '|InternalProperty' : '');
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