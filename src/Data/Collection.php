<?php

declare(strict_types=1);

namespace Stepapo\Model\Data;

use ArrayObject;


class Collection extends ArrayObject
{
	/** @return Collection<Item> */
	public function findByKeys(array $keys): self
	{
		$array = [];
		foreach ($keys as $key) {
			if ($item = $this->getByKey($key)) {
				$array[$key] = $item;
			}
		}
		return new self($array);
	}


	public function getByKey(mixed $key): ?Item
	{
		return $this[$key] ?? null;
	}
}
