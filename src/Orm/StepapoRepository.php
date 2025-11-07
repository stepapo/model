<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm;

use Build\Model\Person\Person;
use DateTimeInterface;
use Nette\DI\Attributes\Inject;
use Nette\Utils\Strings;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Orm\Collection\Functions\CollectionFunction;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Repository\IDependencyProvider;
use Nextras\Orm\Repository\Repository;
use ReflectionClass;
use ReflectionException;
use Stepapo\Model\Data\Item;
use Stepapo\Model\Orm\Functions\StepapoOrmFunction;
use Stepapo\Utils\Injectable;
use Webovac\Core\Model\CmsEntity;


abstract class StepapoRepository extends Repository implements Injectable
{
	#[Inject] public StepapoConditionParser $conditionParser;
	/** @var StepapoOrmFunction[] */ private array $functions;


	/** @param StepapoOrmFunction[] $functions */
	public function __construct(
		IMapper $mapper,
		IDependencyProvider|null $dependencyProvider = null,
		array $functions = []
	) {
		foreach ($functions as $function) {
			$this->functions[$function::class] = $function;
		}
		parent::__construct($mapper, $dependencyProvider);
	}


	public function create(): IEntity
	{
		$class = new ReflectionClass($this->getEntityClassName([]));
		$entity = $class->newInstance();
		$this->attach($entity);
		return $entity;
	}


	public function createCollectionFunction(string $name): CollectionFunction
	{
		if (isset($this->functions[$name])) {
			return $this->functions[$name];
		} else {
			return parent::createCollectionFunction($name);
		}
	}


	/**
	 * @throws ReflectionException
	 */
	public function createFromDataAndReturnResult(
		Item $data,
		?StepapoEntity $original = null,
		?Person $person = null,
		?DateTimeInterface $date = null,
		bool $fromNeon = false,
	): EntityProcessorResult
	{
		$entity = $original ?: $this->create();
		$processor = new EntityProcessor($entity, $data, $person, $date, $this->getModel(), $fromNeon);
		return $processor->processEntity();
	}


	/**
	 * @throws ReflectionException
	 */
	public function createFromData(
		Item $data,
		?StepapoEntity $original = null,
		?Person $person = null,
		?DateTimeInterface $date = null,
		bool $fromNeon = false,
	): StepapoEntity
	{
		$result = $this->createFromDataAndReturnResult($data, $original, $person, $date, $fromNeon);
		return $result->entity;
	}


	public function getById($id): ?CmsEntity
	{
		try {
			return parent::getById($id);
		} catch (QueryException $e) {
			return null;
		}
	}


	public function delete(CmsEntity $entity): void
	{
		$this->onBeforeRemove($entity);
		$this->mapper->delete($entity);
		$this->onAfterRemove($entity);
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


	public function getConditionParser(): StepapoConditionParser
	{
		return $this->conditionParser;
	}
}
