<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Stepapo\Utils\Attribute\HideInApi;


class ToArrayConverter
{
	public const int RELATIONSHIP_AS_IS = 1;
	public const int RELATIONSHIP_AS_ID = 2;
	public const int RELATIONSHIP_AS_ARRAY = 3;
	public const int MAX_RECURSION_LEVEL = 2;


	public static function toArray(
		IEntity $entity,
		int $type = self::RELATIONSHIP_AS_IS,
		?array $select = null,
		int $recursionLevel = 0,
	): array
	{
		$return = [];
		$metadata = $entity->getMetadata();
		$select = static::normalizeSelect((array) $select);

		foreach (static::sortProperties($metadata->getProperties()) as $name => $metadataProperty) {
			if (($select && !isset($select[$name])) || array_key_exists(HideInApi::class, $metadataProperty->types)) {
				continue;
			}
			if (!$entity->hasValue($name)) {
				$value = null;
			} else {
				$value = $entity->getValue($name);
			}
			if ($value instanceof IEntity) {
				if ($select) {
					$value = static::toArray($value, $type, $select[$name] === true ? [] : $select[$name], $recursionLevel + 1);
				} else {
					if ($type === self::RELATIONSHIP_AS_ID || $recursionLevel + 1 >= static::MAX_RECURSION_LEVEL) {
						$value = $value->getValue('id');
					} elseif ($type === self::RELATIONSHIP_AS_ARRAY) {
						$value = static::toArray($value, $type, [], $recursionLevel + 1);
					}
				}
			} elseif ($value instanceof IRelationshipCollection) {
				if ($select) {
					$collection = [];
					foreach ($value as $collectionEntity) {
						$collection[$collectionEntity->getPersistedId()] = static::toArray($collectionEntity, $type, $select[$name] === true ? [] : $select[$name], $recursionLevel + 1);
					}
					$value = $collection;
				} else {
					continue;
				}
			}
			$return[isset($select[$name]) && is_string($select[$name]) ? $select[$name] : $name] = $value;
		}
		return $return;
	}


	/** @param PropertyMetadata[] $properties */
	public static function sortProperties(array $properties): array
	{
		uasort($properties, function (PropertyMetadata $a, PropertyMetadata $b) {
			$aType = static::getNameFromFqn(key($a->types));
			$bType = static::getNameFromFqn(key($b->types));
			if (
				[$b->isPrimary, in_array($bType, ['array', 'int', 'float', 'bool', 'string'], true), $bType === 'DateTimeImmutable', !in_array($bType, ['OneHasMany', 'ManyHasMany'], true), $bType === 'OneHasMany', $bType === 'ManyHasMany']
				===
				[$a->isPrimary, in_array($aType, ['array', 'int', 'float', 'bool', 'string'], true), $aType === 'DateTimeImmutable', !in_array($aType, ['OneHasMany', 'ManyHasMany'], true), $aType === 'OneHasMany', $aType === 'ManyHasMany']
			) {
				if ($aType === $bType) {
					return strcmp($a->name, $b->name);
				}
				return strcmp($aType, $bType);
			}
			return [$b->isPrimary, in_array($bType, ['array', 'int', 'float', 'bool', 'string'], true), $bType === 'DateTimeImmutable', !in_array($bType, ['OneHasMany', 'ManyHasMany'], true), $bType === 'OneHasMany', $bType === 'ManyHasMany']
				<=>
				[$a->isPrimary, in_array($aType, ['array', 'int', 'float', 'bool', 'string'], true), $aType === 'DateTimeImmutable', !in_array($aType, ['OneHasMany', 'ManyHasMany'], true), $aType === 'OneHasMany', $aType === 'ManyHasMany'];
		});
		return $properties;
	}


	public static function getNameFromFqn(string $fqn): string
	{
		$parts = explode('\\', $fqn);
		return end($parts);
	}


	private static function normalizeSelect(array $select): array
	{
		$result = [];
		foreach ($select as $key => $value) {
			if (is_numeric($key) && is_string($value)) {
				$k = $value;
				$v = true;
			} else {
				$k = $key;
				$v = is_array($value) ? (static::normalizeSelect($value) ?: true) : $value;
			}
			static::storeToTree($k, $v, $result);
		}
		return $result;
	}


	/**
	 * for $key equals "a.b.c" stores $value to $tree["a"]["b"]["c"]
	 */
	private static function storeToTree(string $key, mixed $value, array &$tree, string $delimiter = ".")
	{
		if (str_contains($key, $delimiter)) {
			$temp = &$tree;
			$exploded = explode('.', $key);
			foreach ($exploded as $k) {
				$temp = &$temp[$k];
			}
			$temp = $value;
			unset($temp);
		} else {
			$tree[$key] = $value;
		}
	}
}
