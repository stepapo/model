<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm;

use App\Model\Person\Person;
use DateTimeInterface;
use Nette\Utils\Strings;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Repository\Repository;
use ReflectionClass;
use ReflectionException;
use Stepapo\Model\Data\Item;


abstract class StepapoRepository extends Repository
{
	/**
	 * @throws ReflectionException
	 */
	public function createFromDataAndReturnResult(
		Item $data,
		?StepapoEntity $original = null,
		?StepapoEntity $parent = null,
		?string $parentName = null,
		?Person $person = null,
		?DateTimeInterface $date = null,
		bool $skipDefaults = false,
		bool $getOriginalByData = false,
		bool $fromNeon = false,
	): EntityProcessorResult
	{
		if ($getOriginalByData) {
			$original ??= method_exists($this, 'getByData') ? $this->getByData($data, $parent) : null;
		}
		$class = new ReflectionClass($this->getEntityClassName([]));
		$entity = $original ?: $class->newInstance();
		$processor = new EntityProcessor($entity, $data, $person, $date, $skipDefaults, $this->getModel(), $fromNeon);
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
		bool $fromNeon = false,
	): StepapoEntity
	{
		if ($getOriginalByData) {
			$original ??= method_exists($this, 'getByData') ? $this->getByData($data, $parent) : null;
		}
		$class = new ReflectionClass($this->getEntityClassName([]));
		$entity = $original ?: $class->newInstance();
		$processor = new EntityProcessor($entity, $data, $person, $date, $skipDefaults, $this->getModel(), $fromNeon);
		return $processor->processEntity($parent, $parentName)->entity;
	}


	public function createSlug(string $title, ?Entity $entity = null, string $separator = '-', int $num = 1): string
	{
		$slug = Strings::webalize($title) . ($num > 1 ? '-' . $num : '');
		$filter = ['slug' => $slug];
		if ($entity) {
			$filter['id!='] = $entity->getPersistedId();
		}
		if ($this->getBy($filter)) {
			return $this->createSlug($title, $entity, $separator, $num + 1);
		}
		return $separator === '-' ? $slug : str_replace('-', $separator, $slug);
	}
}
