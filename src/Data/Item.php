<?php

declare(strict_types=1);

namespace Stepapo\Model\Data;

use App\Model\Text\TextData;
use InvalidArgumentException;
use ReflectionClass;
use Stepapo\Utils\ReflectionHelper;
use Stepapo\Utils\Config;


class Item extends Config
{
	/**
	 * @throws \ReflectionException
	 */
	public function getCollection(string $name): Collection
	{
		$rf = new ReflectionClass($this);
		$prop = $rf->getProperty($name);
		if (!property_exists($this, $name) || !$this->isCollection($name)) {
			throw new InvalidArgumentException("Property '$name' does not exist or is not a collection.");
		}
		return new Collection($prop->isInitialized($this) ? $this->$name : []);
	}


	/**
	 * @throws \ReflectionException
	 */
	public function isCollection(string $name): bool
	{
		$rf = new ReflectionClass($this);
		$prop = $rf->getProperty($name);
		return ReflectionHelper::propertyHasType($prop, 'array');
	}
}