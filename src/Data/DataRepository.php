<?php

declare(strict_types=1);

namespace Stepapo\Model\Data;

use App\Model\Orm;
use Nette\Caching\Cache;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Repository\IRepository;
use Stepapo\Utils\Injectable;
use Stepapo\Utils\Service;
use Webovac\Core\Lib\CmsCache;


abstract class DataRepository implements Service, Injectable
{
	/** @var Collection<Item> */ protected Collection $collection;


	public function __construct(
		protected Orm $orm,
		protected CmsCache $cmsCache,
		protected Cache $cache,
	) {}


	/** @return Collection<Item> */
	public function findByKeys(array $keys): Collection
	{
		$array = [];
		foreach ($keys as $key) {
			$this->addItemToCollection($key);
			$array[] = $this->collection[$key];
		}
		return new Collection($array);
	}


	public function getByKey(mixed $key): ?Item
	{
		$this->addItemToCollection($key);
		return $this->collection[$key];
	}


	public function buildCache(): void
	{
		if (isset($this->collection)) {
			return;
		}
		$this->cmsCache->remove(lcfirst($this->getName()) . 'Aliases');
		$this->cmsCache->clean([Cache::Tags => lcfirst($this->getName())]);
		foreach ($this->getOrmRepository()->findAll() as $entity) {
			$key = $this->getIdentifier($entity);
			$item = $entity->getData(forCache: true);
			$this->cacheItem($key, $item);
			$this->addItemToCollection($key, $item);
		}
	}


	protected function getIdentifier(IEntity $entity): mixed
	{
		return $entity->getPersistedId();
	}


	protected function getName(): string
	{
		$className = preg_replace('~^.+\\\\~', '', get_class($this));
		assert($className !== null);
		return str_replace('DataRepository', '', $className);
	}


	protected function getOrmRepository(): IRepository
	{
		$name = $this->getName();
		return $this->orm->getRepository("App\\Model\\$name\\{$name}Repository");
	}


	/** @return Collection<Item> */
	public function getCollection(): Collection
	{
		if (!isset($this->collection)) {
			$this->buildCache();
		}
		return $this->collection;
	}


	public function cacheItem(mixed $key, Item $item): void
	{
		$this->cache->save(
			lcfirst($this->getName()) . '/' . $key,
			$item,
			[Cache::Tags => lcfirst($this->getName())],
		);
	}


	protected function addItemToCollection(mixed $key, ?Item $item = null): void
	{
		if (!isset($this->collection) || !array_key_exists($key, (array) $this->collection)) {
			$item = $this->cache->load(lcfirst($this->getName()) . "/$key");
			if (!$item) {
				$this->buildCache();
				$item = $this->collection[$key] ?? null;
			}
			if (!isset($this->collection)) {
				$this->collection = new Collection;
			}
			if (!array_key_exists($key, (array) $this->collection)) {
				$this->collection[$key] = $item;
			}
		}
	}
}