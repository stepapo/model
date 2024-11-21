<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm;

use App\Model\File\File;
use App\Model\File\FileData;
use App\Model\File\FileRepository;
use App\Model\Person\Person;
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


class EntityProcessor
{
	public bool $isPersisted = false;
	public bool $isModified = false;


	public function __construct(
		public StepapoEntity $entity,
		private Item $data,
		private ?Person $person,
		private ?DateTimeInterface $date,
		private bool $skipDefaults,
		private IModel $model,
	) {}


	public function processEntity(?StepapoEntity $parent = null, ?string $parentName = null): bool
	{
		$metadata = $this->entity->getMetadata();
		if ($parent && $parentName) {
			if (!isset($this->entity->$parentName) || $this->entity->$parentName !== $parent) {
				$this->entity->$parentName = $parent;
			}
		}
		foreach ($this->data as $name => $value) {
			if (in_array($name, ['createdAt', 'updatedAt', 'createdByPerson', 'updatedByPerson'], true)) {
				continue;
			}
			$property = $metadata->hasProperty($name) ? $metadata->getProperty($name) : null;
			if (!$property || $property->isPrimary) {
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
		return $this->isModified;
	}


	public function processScalar(PropertyMetadata $property): void
	{
		$name = $property->name;
		$value = $this->data->$name;
		if ((empty($this->entity->$name) && !empty($value)) || $this->entity->$name !== $value) {
			$this->entity->$name = $value;
		}
	}


	public function processHasOne(PropertyMetadata $property): void
	{
		$name = $property->name;
		$relatedRepository = $this->model->getRepository($property->relationship->repository);
		if (isset($property->types[File::class])) {
			$value = $this->data->$name instanceof FileData
				? $relatedRepository->getById($this->data->id)
				: (
					$this->model->getRepository(FileRepository::class)->createFile($this->data->$name, $this->person, $name === 'iconFile')
						?: $this->entity->$name
				);
		} elseif ($this->data->$name instanceof Item) {
			$related = null;
			if (method_exists($relatedRepository, 'getByData')) {
				$related = $relatedRepository->getByData($this->data->$name, $this->entity);
			}
			if (!$related) {
				$related = $relatedRepository->createFromData($this->data->$name, person: $this->person, date: $this->date);
			}
			$value = $related;
		} elseif (is_numeric($this->data->$name)) {
			$value = $this->data->$name ? $relatedRepository->getById($this->data->$name) : null;
		} elseif (method_exists($relatedRepository, 'getByData')) {
			$related = $this->data->$name ? $relatedRepository->getByData($this->data->$name, $this->entity) : null;
			if (!$related && method_exists($relatedRepository, 'createFromString')) {
				$related = $relatedRepository->createFromString($this->data->$name);
			}
			$value = $related;
		}
		if (isset($value) && (!isset($this->entity->$name) || $this->entity->$name !== $value)) {
			$this->entity->$name = $value;
		}
	}


	public function processManyHasMany(PropertyMetadata $property): void
	{
		$name = $property->name;
		$relatedRepository = $this->model->getRepository($property->relationship->repository);
		$array = [];
		foreach ((array) $this->data->$name as $item) {
			if (isset($property->types[File::class])) {
				$array[] = $this->model->getRepository(FileRepository::class)->createFile($item, $this->person);
			} elseif (is_numeric($item)) {
				if ($item = $relatedRepository->getById($item)) {
					$array[] = $item;
				}
			} elseif (method_exists($relatedRepository, 'getByData')) {
				if ($item = $relatedRepository->getByData($item, $this->entity)) {
					$array[] = $item;
				}
			}
		}
		$oldIds = $this->entity->$name?->toCollection()->fetchPairs(null, 'id');
		$newIds = array_map(fn($v) => $v->id, $array);
		sort($oldIds);
		sort($newIds);
		if (!isset($this->entity->$name) || $oldIds !== $newIds) {
			$this->isModified = true;
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
		foreach ((array) $this->data->$name as $relatedData) {
			$relatedOriginal = method_exists($relatedRepository, 'getByData') ? $relatedRepository->getByData($relatedData, $this->entity) : null;
			$relatedEntity = $relatedOriginal ?: $relatedClass->newInstance();
			$processor = new self($relatedEntity, $relatedData, $this->person, $this->date, $this->skipDefaults, $this->model);
			$processor->processEntity(parent: $this->entity, parentName: $property->relationship->property);
			if (!$this->isModified) {
				$this->isModified = $processor->isModified;
			}
			$ids[] = $relatedEntity->getPersistedId();
		}
		if (!$this->skipDefaults) {
			foreach ($this->entity->$name as $related) {
				if (!in_array($related->getPersistedId(), $ids, true)) {
					$this->isModified = true;
					$relatedRepository->delete($related);
				}
			}
		}
	}
}