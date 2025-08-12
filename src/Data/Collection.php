<?php

declare(strict_types=1);

namespace Stepapo\Model\Data;

use ArrayObject;


class Collection extends ArrayObject
{
	/** @return Collection<Item> */
	public function findByKeys(array $keys): Collection
	{
		$array = [];
		foreach ($keys as $key) {
			if ($item = $this->getById($key)) {
				$array[$key] = $item;
			}
		}
		return new Collection($array);
	}


	public function getByKey(mixed $key): ?Item
	{
		return $this[$key] ?? null;
	}
}