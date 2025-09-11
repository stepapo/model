<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm;

use Nette\NotSupportedException;
use Nette\Utils\Type;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\ManyHasMany;
use Nextras\Orm\Relationships\ManyHasOne;
use Nextras\Orm\Relationships\OneHasMany;
use Nextras\Orm\Relationships\OneHasOne;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Stepapo\Model\Data\Item;
use Stepapo\Utils\Attribute\DontCache;
use Stepapo\Utils\Injectable;


abstract class StepapoEntity extends Entity implements Injectable
{
	public function toArray(int $mode = ToArrayConverter::RELATIONSHIP_AS_IS, ?array $select = null, callable|null $checkProperty = null): array
	{
		return ToArrayConverter::toArray($this, $mode, $select, checkProperty: $checkProperty);
	}


	/**
	 * @throws NotSupportedException
	 * @throws ReflectionException
	 */
	public function getData(bool $neon = false, bool $forCache = false, ?array $select = null): Item|array
	{
		if (!method_exists($this, 'getDataClass')) {
			throw new NotSupportedException('Entity does not have data class defined.');
		}
		$class = new ReflectionClass($this->getDataClass());
		$data = $class->newInstance();
		foreach ($class->getProperties() as $p) {
			$name = $p->name;
			if ($select && !isset($select[$name])) {
				continue;
			}
			$property = $this->getMetadata()->hasProperty($name) ? $this->getMetadata()->getProperty($name) : null;
			if (!$property) {
				continue;
			} elseif ($forCache && $p->getAttributes(DontCache::class)) {
				continue;
			} elseif (!$property->wrapper) {
				$data->$name = $this->$name;
			} elseif (in_array($property->wrapper, [OneHasOne::class, ManyHasOne::class])) {
				$data->$name = $this->shouldGetData($p) ? $this->$name?->getData($neon, $forCache) : $this->$name?->getPersistedId();
			} elseif ($property->wrapper === OneHasMany::class) {
				foreach ($this->$name as $related) {
					$relatedData = $related->getData($neon, $forCache);
					$keyProperty = $relatedData::getKeyProperty();
					if ($keyProperty && $related->$keyProperty instanceof StepapoEntity) {
						$id = $related->$keyProperty->getPersistedId();
					} else {
						$id = $related->getPersistedId();
					}
					$data->$name[$id] = $related->getData($neon, $forCache);
//					$data->$name[$keyProperty ? $relatedData->$keyProperty : $related->getPersistedId()] = $related->getData($neon, $forCache);
				}
			} elseif ($property->wrapper === ManyHasMany::class) {
				foreach ($this->$name as $related) {
					$data->$name[] = $related->getPersistedId();
				}
			}
		}
		return $data;
	}


	/**
	 * @throws NotSupportedException
	 */
	public function getTitle(): string
	{
		if (!$this->getMetadata()->hasProperty('title')) {
			throw new NotSupportedException;
		}
		return $this->title;
	}


	/**
	 * @throws ReflectionException
	 */
	private function shouldGetData(ReflectionProperty $property): bool
	{
		$types = Type::fromReflection($property)->getTypes();
		foreach ($types as $type) {
			if (!$type->isClass()) {
				continue;
			}
			if ((new ReflectionClass($type->getSingleName()))->isSubclassOf(Item::class)) {
				return true;
			}
		}
		return false;
	}
}
