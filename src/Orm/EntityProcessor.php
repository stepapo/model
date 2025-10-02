<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm;

use Build\Model\File\FileData;
use Build\Model\File\FileRepository;
use Build\Model\Person\Person;
use DateTimeInterface;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Relationships\ManyHasMany;
use Nextras\Orm\Relationships\ManyHasOne;
use Nextras\Orm\Relationships\OneHasMany;
use Nextras\Orm\Relationships\OneHasOne;
use ReflectionClass;
use ReflectionException;
use Stepapo\Model\Data\Item;
use Stepapo\Utils\Attribute\SkipInManipulation;


class EntityProcessor
{
	public bool $isPersisted = false;
	public bool $isModified = false;
	public DiffList $modifiedValues;


	public function __construct(
		public StepapoEntity $entity,
		private Item $data,
		private ?Person $person,
		private ?DateTimeInterface $date,
		private bool $skipDefaults,
		private IModel $model,
		private bool $fromNeon = false,
		private bool $isRelated = false,
	) {}


	public function processEntity(?StepapoEntity $parent = null, ?string $parentName = null): EntityProcessorResult
	{
		$this->modifiedValues = new DiffList;
		$metadata = $this->entity->getMetadata();
		if ($parent && $parentName) {
			if (!isset($this->entity->$parentName) || $this->entity->$parentName !== $parent) {
				$this->entity->$parentName = $parent;
			}
		}
		$class = new ReflectionClass($this->entity->getDataClass());
		foreach ($this->data as $name => $value) {
			if (in_array($name, ['createdAt', 'updatedAt', 'createdByPerson', 'updatedByPerson', $parentName], true)) {
				continue;
			}
			$dataProperty = $class->getProperty($name);
			if ($this->fromNeon && $dataProperty->getAttributes(SkipInManipulation::class)) {
				continue;
			}
			$property = $metadata->hasProperty($name) ? $metadata->getProperty($name) : null;
			if (!$property || (!isset($this->data->$name) && $property->isPrimary)) {
				continue;
			} elseif (!$property->wrapper || $property->wrapper === DateTimeImmutable::class) {
				$this->processScalar($property);
			} elseif (in_array($property->wrapper, [OneHasOne::class, ManyHasOne::class])) {
				$this->processHasOne($property);
			} elseif ($property->wrapper === ManyHasMany::class) {
				$this->processManyHasMany($property);
			}
		}
		$this->isModified = $this->isModified || $this->entity->isModified();
		$this->isPersisted = $this->entity->isPersisted();
		if (!$this->isPersisted) {
			if ($metadata->hasProperty('createdByPerson')) {
				$this->entity->createdByPerson = $this->person;
			}
			$this->model->persist($this->entity);
		}
		foreach ($this->data as $name => $value) {
			$dataProperty = $class->getProperty($name);
			if ($this->fromNeon && $dataProperty->getAttributes(SkipInManipulation::class)) {
				continue;
			}
			$property = $metadata->hasProperty($name) ? $metadata->getProperty($name) : null;
			if (!$property) {
				continue;
			} elseif ($property->wrapper === OneHasMany::class) {
				$this->processOneHasMany($property);
			}
		}
		if ($this->isPersisted && $this->isModified) {
			if ($metadata->hasProperty('updatedByPerson')) {
				$this->entity->updatedByPerson = $this->person;
			}
			if ($metadata->hasProperty('updatedAt')) {
				$this->entity->updatedAt = $this->date;
			}
			// kvůli indexu při změně translation:
			if ($this->isModified && !$this->entity->isModified()) {
				$this->entity->getRepository()->onAfterUpdate($this->entity);
			}
			$this->model->persist($this->entity);
		}
		// kvůli indexu při změně translation:
		if ($this->isModified && !$this->entity->isModified()) {
			$this->entity->getRepository()->onAfterPersist($this->entity);
		}
		return new EntityProcessorResult($this->entity, $this->isModified, $this->modifiedValues);
	}


	public function processScalar(PropertyMetadata $property): void
	{
		$name = $property->name;
		$value = $this->data->$name;
		if ($property->wrapper === DateTimeImmutable::class) {
			if ((empty($this->entity->$name) && (!empty($value) || $value === '0')) || $this->entity->$name != $value) {
				$this->modifiedValues->propertyList[$name] = ['old' => empty($this->entity->$name) ? null : $this->entity->$name->format('Y-m-d H:i:s'), 'new' => $value->format('Y-m-d H:i:s')];
				$this->entity->$name = $value;
			}
		} else {
			if ((empty($this->entity->$name) && (!empty($value) || $value === '0')) || $this->entity->$name !== $value) {
				$this->modifiedValues->propertyList[$name] = ['old' => empty($this->entity->$name) ? null : $this->entity->$name, 'new' => $value];
				$this->entity->$name = $value;
			}
		}
	}


	public function processHasOne(PropertyMetadata $property): void
	{
		$name = $property->name;
		$relatedRepository = $this->model->getRepository($property->relationship->repository);
		$relatedClass = new ReflectionClass($relatedRepository->getEntityClassName([]));
		if ($this->data->$name instanceof Item) {
			if ($this->data->$name instanceof FileData) {
				$this->data->$name = $this->model->getRepository(FileRepository::class)->createFileData($this->data->$name);
			}
			$relatedOriginal = method_exists($relatedRepository, 'getByData') ? $relatedRepository->getByData($this->data->$name/*, $this->entity*/) : null;
			$relatedEntity = $relatedOriginal ?: $relatedClass->newInstance();
			$processor = new self($relatedEntity, $this->data->$name, $this->person, $this->date, $this->skipDefaults, $this->model, $this->fromNeon, true);
			$processor->processEntity();
			if ($processor->isModified) {
				$this->isModified = $processor->isModified;
			}
			$value = $relatedEntity;
		} elseif (is_numeric($this->data->$name)) {
			$value = $this->data->$name ? $relatedRepository->getById($this->data->$name) : null;
		} elseif (method_exists($relatedRepository, 'getByData')) {
			$related = $this->data->$name ? $relatedRepository->getByData($this->data->$name, $this->entity) : null;
			if (!$related && method_exists($relatedRepository, 'createFromString')) {
				$related = $relatedRepository->createFromString($this->data->$name);
			}
			$value = $related;
		}
		if (
			(isset($value) && (!isset($this->entity->$name) || $this->entity->$name !== $value))
			|| ($value === null && $this->entity->$name !== null/* && !$this->entity instanceof PostProcessable*/)
		) {
			$this->modifiedValues->propertyList[$name] = ['old' => '', 'new' => $value?->getPersistedId()];
			$this->entity->$name = $value;
		}
	}


	public function processManyHasMany(PropertyMetadata $property): void
	{
		$name = $property->name;
		$relatedRepository = $this->model->getRepository($property->relationship->repository);
		$array = [];
		foreach ((array) $this->data->$name as $item) {
			if (is_numeric($item)) {
				if ($related = $relatedRepository->getById($item)) {
					$array[] = $related;
				}
			} elseif (method_exists($relatedRepository, 'getByData')) {
				if ($related = $relatedRepository->getByData($item, $this->entity)) {
					$array[] = $related;
				} elseif (is_string($item) && method_exists($relatedRepository, 'createFromString')) {
					$array[] = $relatedRepository->createFromString($item);
				}
			}
		}
		$oldIds = $this->entity->$name?->toCollection()->fetchPairs(null, 'id');
		$newIds = array_map(fn($v) => $v->id, $array);
		sort($oldIds);
		sort($newIds);
		if (!isset($this->entity->$name) || $oldIds !== $newIds) {
			$this->isModified = true;
			$this->modifiedValues->propertyList[$name] = ['old' => implode(',', $oldIds), 'new' => implode(',', $newIds)];
			$this->entity->$name->set($array);
		}
	}


	/**
	 * @throws ReflectionException
	 */
	public function processOneHasMany(PropertyMetadata $property): void
	{
		$name = $property->name;
		$ids = [];
		$relatedRepository = $this->model->getRepository($property->relationship->repository);
		$relatedClass = new ReflectionClass($relatedRepository->getEntityClassName([]));
		foreach ((array) $this->data->$name as $key => $relatedData) {
			if ($relatedData instanceof FileData) {
				$relatedData = $this->model->getRepository(FileRepository::class)->createFileData($relatedData, !is_numeric($key) ? $key : null);
				if (!$relatedData) {
					continue;
				}
			}
//			if (is_numeric($key)) {
//				$relatedOriginal = $relatedRepository->getById($key);
//			} else {
				$relatedOriginal = method_exists($relatedRepository, 'getByData') ? $relatedRepository->getByData($relatedData, $this->entity) : null;
//			}
			$relatedEntity = $relatedOriginal ?: $relatedClass->newInstance();
			$processor = new self($relatedEntity, $relatedData, $this->person, $this->date, $this->skipDefaults, $this->model, $this->fromNeon, true);
			$processor->processEntity(parent: $this->entity, parentName: $property->relationship->property);
			if ($processor->isModified) {
				$this->isModified = $processor->isModified;
				$this->modifiedValues->propertyList[$name] ??= new DiffList;
				$this->modifiedValues->propertyList[$name]->entityList[$key] = $processor->modifiedValues;
			}
			$ids[] = $relatedEntity->getPersistedId();
		}
		if (!$this->skipDefaults) {
			foreach ($this->entity->$name as $related) {
				if (!in_array($related->getPersistedId(), $ids, true)) {
					$this->isModified = true;
					$this->modifiedValues->propertyList[$name] ??= new DiffList;
					$this->modifiedValues->propertyList[$name]->propertyList['removedCount'] = ($this->modifiedValues->propertyList[$name]->propertyList['removedCount'] ?? 0) + 1;
					$relatedRepository->delete($related);
				}
			}
		}
	}
}
