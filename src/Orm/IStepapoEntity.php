<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Stepapo\Model\Data\Item;


/**
 * @method StepapoRepository getRepository()
 */
interface IStepapoEntity extends IEntity
{
	/**
	 * @param callable(PropertyMetadata): bool|null $checkProperty
	 */
	function toArrayWithSelect(
		int $mode = ToArrayConverter::RELATIONSHIP_AS_IS,
		?array $select = null,
		?callable $checkProperty = null,
	): array;

	function getData(bool $neon = false, bool $forCache = false): Item;

	function getTitle(): string;

	function getDataClass(): string;
}
