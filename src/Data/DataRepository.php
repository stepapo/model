<?php

declare(strict_types=1);

namespace Stepapo\Model\Data;

use Build\Model\Orm;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Repository\IRepository;
use Stepapo\Model\Orm\StepapoEntity;
use Stepapo\Utils\Injectable;
use Stepapo\Utils\Service;
use Webovac\Core\Lib\CmsCache;


abstract class DataRepository implements Service, Injectable
{
	/** @var Collection<Item> */ protected Collection $collection;
	/** @var Collection<Item> */ protected Collection $loadedCollection;
	protected Cache $cache;


	public function __construct(
		protected Orm $orm,
		protected CmsCache $cmsCache,
		protected Storage $storage,
	) {
		$this->cache = new Cache($this->storage, 'cms.' . lcfirst($this->getName()));
	}


	/** @return Collection<Item> */
	public function findByKeys(array $keys): Collection
	{
		$array = [];
		foreach ($keys as $key) {
			$this->addItemToCollection($key);
			$array[] = $this->loadedCollection[$key];
		}
		return new Collection($array);
	}


	public function getByKey(mixed $key): ?Item
	{
		$this->addItemToCollection($key);
		return $this->loadedCollection[$key];
	}


	public function buildCache(): void
	{
		$this->cmsCache->clean([Cache::Tags => lcfirst($this->getName())]);
		$collection = new Collection;
		/** @var StepapoEntity $entity */
		foreach ($this->getOrmRepository()->findAll() as $entity) {
			$key = $this->getIdentifier($entity);
			$item = $entity->getData(forCache: true);
			$this->cacheItem($key, $item);
			$collection[$key] = $item;
		}
		$this->collection = $collection;
		$this->cache->save('collection', $collection, [Cache::Tags => lcfirst($this->getName())]);
		$this->setReady();
	}


	protected function getIdentifier(IEntity $entity): mixed
	{
		return $entity->getPersistedId();
	}


	protected function getName(): string
	{
		$className = preg_replace('~^.+\\\\~', '', get_class($this));
		return str_replace('DataRepository', '', $className);
	}


	protected function getOrmRepository(): IRepository
	{
		$name = lcfirst($this->getName());
		return $this->orm->getRepositoryByName("{$name}Repository");
	}


	/** @return Collection<Item> */
	public function getCollection(): Collection
	{
		if (!isset($this->collection)) {
			$this->collection = $this->cache->load('collection', function() {
				$this->buildCache();
				return $this->collection;
			}, [Cache::Tags => lcfirst($this->getName())]);
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


	public function removeItem(mixed $key): void
	{
		$this->cache->remove(lcfirst($this->getName()) . '/' . $key);
	}


	protected function addItemToCollection(mixed $key, ?Item $item = null): void
	{
		if (!isset($this->loadedCollection) || !array_key_exists($key, (array) $this->loadedCollection)) {
			$item ??= $this->cache->load(lcfirst($this->getName()) . "/$key");
			if (!$item && !$this->isReady()) {
				$this->buildCache();
				$item = $this->collection[$key] ?? null;
			}
			if (!isset($this->loadedCollection)) {
				$this->loadedCollection = new Collection;
			}
			if (!array_key_exists($key, (array) $this->loadedCollection)) {
				$this->loadedCollection[$key] = $item;
			}
		}
	}


	protected function isReady(): bool
	{
		return (bool) $this->cache->load('ready');
	}


	protected function setReady(bool $ready = true): void
	{
		$this->cache->save('ready', $ready, [Cache::Tags => lcfirst($this->getName())]);
	}
}
