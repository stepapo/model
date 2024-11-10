<?php

declare(strict_types=1);

namespace Stepapo\Model\Data;


abstract class Repository
{
	/** @var Collection<Item> */ protected Collection $collection;


	/** @return Collection<Item> */
	abstract protected function getCollection(): Collection;


	/** @return Collection<Item> */
	public function findAll(): Collection
	{
		return $this->getCollection()->findAll();
	}


	/** @return Collection<Item> */
	public function findBy(array $conds): Collection
	{
		return $this->getCollection()->findBy($conds);
	}


	public function getById(mixed $id): ?Item
	{
		return $this->getCollection()->getById($id);
	}


	public function getBy(array $conds): ?Item
	{
		return $this->getCollection()->getBy($conds);
	}
}