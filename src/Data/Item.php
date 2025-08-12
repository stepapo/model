<?php

declare(strict_types=1);

namespace Stepapo\Model\Data;

use InvalidArgumentException;
use ReflectionClass;
use Stepapo\Utils\Config;
use Stepapo\Utils\ReflectionHelper;


class Item extends Config
{
	/** @throws \ReflectionException */
	public function getCollection(string $name): Collection
	{
		$rf = new ReflectionClass($this);
		$prop = $rf->getProperty($name);
		if (!property_exists($this, $name) || !ReflectionHelper::propertyHasType($prop, 'array')) {
			throw new InvalidArgumentException("Property '$name' does not exist or is not a collection.");
		}
		return new Collection($prop->isInitialized($this) ? $this->$name : []);
	}
}