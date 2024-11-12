<?php

declare(strict_types=1);

namespace Stepapo\Model\Manipulation\Config;

use Nette\InvalidArgumentException;
use Nette\Schema\ValidationException;
use Stepapo\Model\Data\Item;
use Stepapo\Utils\Schematic;


class Manipulation extends Schematic
{
	public string $class;
	public array $modes = ['prod', 'dev', 'test'];
	public bool $forceUpdate = true;
	public bool $override = false;
	public int $iteration = 1;
	/** @var Item[]|array */ public array $items;


	public static function createFromArray(mixed $config = [], mixed $key = null, bool $skipDefaults = false, mixed $parentKey = null): static
	{
		$manipulation = parent::createFromArray($config, $key, $skipDefaults);
		foreach ($manipulation->items as $itemKey => $itemConfig) {
			try {
				$manipulation->items[$itemKey] = $manipulation->class::createFromArray(
					$itemConfig,
					$itemKey,
					$skipDefaults,
					$parentKey,
				);
			} catch (ValidationException $e) {
				throw new InvalidArgumentException("$itemKey: " . $e->getMessage());
			}
		}
		return $manipulation;
	}
}