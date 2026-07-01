<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm;

use Build\Model\Person\Person;
use DateTimeInterface;
use Nextras\Orm\Collection\Functions\CollectionFunction;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Repository\IRepository;
use Stepapo\Model\Data\Item;


/**
 * @method StepapoMapper getMapper()
 */
interface IStepapoRepository extends IRepository
{
	function create(): StepapoEntity;
	function createCollectionFunction(string $name): CollectionFunction;
	function createFromDataAndReturnResult(
		Item $data,
		?IStepapoEntity $original = null,
		?Person $person = null,
		?DateTimeInterface $date = null,
		bool $fromNeon = false,
		string $namespace = 'cms',
	): EntityProcessorResult;
	function createFromData(
		Item $data,
		?IStepapoEntity $original = null,
		?Person $person = null,
		?DateTimeInterface $date = null,
		bool $fromNeon = false,
		string $namespace = 'cms',
	): IStepapoEntity;
	function getById($id): ?StepapoEntity;
	function getBy(array $conds): ?StepapoEntity;
	function delete(StepapoEntity $entity): void;
	function createSlug(string $title, ?Entity $entity = null, string $separator = '-', int $num = 1): string;
	function getConditionParser(): StepapoConditionParser;
}
