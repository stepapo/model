<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm;

use App\Model\Person\Person;
use DateTimeInterface;
use Nextras\Orm\Repository\Repository;
use ReflectionClass;
use ReflectionException;
use Stepapo\Model\Data\Item;


abstract class StepapoRepository extends Repository
{
	/**
	 * @throws ReflectionException
	 */
	public function createFromDataReturnBool(
		Item $data,
		?StepapoEntity $original = null,
		?StepapoEntity $parent = null,
		?string $parentName = null,
		?Person $person = null,
		?DateTimeInterface $date = null,
		bool $skipDefaults = false,
		bool $getOriginalByData = false,
	): bool
	{
		if ($getOriginalByData) {
			$original ??= method_exists($this, 'getByData') ? $this->getByData($data, $parent) : null;
		}
		$class = new ReflectionClass($this->getEntityClassName([]));
		$entity = $original ?: $class->newInstance();
		$processor = new EntityProcessor($entity, $data, $person, $date, $skipDefaults, $this->getModel());
		return $processor->processEntity($parent, $parentName);
	}


	/**
	 * @throws ReflectionException
	 */
	public function createFromData(
		Item $data,
		?StepapoEntity $original = null,
		?StepapoEntity $parent = null,
		?string $parentName = null,
		?Person $person = null,
		?DateTimeInterface $date = null,
		bool $skipDefaults = false,
		bool $getOriginalByData = false,
	): StepapoEntity
	{
		if ($getOriginalByData) {
			$original ??= method_exists($this, 'getByData') ? $this->getByData($data, $parent) : null;
		}
		$class = new ReflectionClass($this->getEntityClassName([]));
		$entity = $original ?: $class->newInstance();
		$processor = new EntityProcessor($entity, $data, $person, $date, $skipDefaults, $this->getModel());
		$processor->processEntity($parent, $parentName);
		return $entity;
	}
}
