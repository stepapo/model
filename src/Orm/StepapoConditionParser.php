<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm;

use Nextras\Orm\Collection\Functions\AvgAggregateFunction;
use Nextras\Orm\Collection\Functions\CompareEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareGreaterThanEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareGreaterThanFunction;
use Nextras\Orm\Collection\Functions\CompareLikeFunction;
use Nextras\Orm\Collection\Functions\CompareNotEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareSmallerThanEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareSmallerThanFunction;
use Nextras\Orm\Collection\Functions\CountAggregateFunction;
use Nextras\Orm\Collection\Functions\MaxAggregateFunction;
use Nextras\Orm\Collection\Functions\MinAggregateFunction;
use Nextras\Orm\Collection\Functions\SumAggregateFunction;
use Nextras\Orm\Collection\Helpers\ConditionParser;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use Stepapo\Model\Orm\Functions\CallableFromProperty;
use Stepapo\Model\Orm\Functions\Comparable;
use Stepapo\Model\Orm\Functions\StepapoOrmFunction;
use Stepapo\Utils\Service;


class StepapoConditionParser extends ConditionParser implements Service
{
	private const array AGGREGATE_FUNCTIONS = [
		'avg' => AvgAggregateFunction::class,
		'count' => CountAggregateFunction::class,
		'max' => MaxAggregateFunction::class,
		'min' => MinAggregateFunction::class,
		'sum' => SumAggregateFunction::class,
	];

	private const array COMPARE_FUNCTIONS = [
		'=' => CompareEqualsFunction::class,
		'!=' => CompareNotEqualsFunction::class,
		'>=' => CompareGreaterThanEqualsFunction::class,
		'>' => CompareGreaterThanFunction::class,
		'<=' => CompareSmallerThanEqualsFunction::class,
		'<' => CompareSmallerThanFunction::class,
		'~'	=> CompareLikeFunction::class,
	];

	/** @var StepapoOrmFunction[] */ private array $functions = [];


	/** @param StepapoOrmFunction[] $functions */
	public function __construct(
		array $functions
	) {
		foreach ($functions as $function) {
			$rc = new \ReflectionClass($function);
			$this->functions[lcfirst($rc->getShortName())] = $function;
		}
		$this->functions = array_merge(self::AGGREGATE_FUNCTIONS, $this->functions);
	}


	public function parsePropertyOperator(string $condition): array
	{
		// language=PhpRegExp
		$regexp = '#^(?P<path>' . self::PATH_REGEXP . ')(:(?P<function>\w+))?(?P<operator>!=|<=|>=|=|>|<|~)?$#';
		if (preg_match($regexp, $condition, $matches) !== 1) {
			return [CompareEqualsFunction::class, $condition];
		}
		$operator = $matches['operator'] ?? '=';
		$function = $matches['function'] ?? null;
		$condition = $this->parsePropertyFunction($condition);
		return !$function || array_key_exists($function, self::AGGREGATE_FUNCTIONS) || $this->functions[$function] instanceof Comparable
			? [self::COMPARE_FUNCTIONS[$operator], $condition]
			: $condition;
	}


	public function parsePropertyFunction(string $propertyPath): array|string
	{
		// language=PhpRegExp
		$regexp = '#^(?P<path>' . self::PATH_REGEXP . ')(:(?P<function>\w+))?(?P<operator>!=|<=|>=|=|>|<|~)?$#';
		if (preg_match($regexp, $propertyPath, $matches) !== 1) {
			throw new InvalidArgumentException('Unsupported condition format.');
		}
		$path = $matches['path'];
		$function = $matches['function'] ?? null;
		if ($function) {
			$collectionFunction = $this->functions[$function] ?? null;
			if (!$collectionFunction || (!array_key_exists($function, self::AGGREGATE_FUNCTIONS) && !$collectionFunction instanceof CallableFromProperty)) {
				throw new InvalidArgumentException("Function '$function' does not exist.");
			}
		}
		return $function
			? [is_string($collectionFunction) ? $collectionFunction : $collectionFunction::class, $path]
			: $path;
	}


	/**
	 * @return array{list<string>, class-string<IEntity>|null}
	 */
	public function parsePropertyExpr(string $propertyPath): array
	{
		// language=PhpRegExp
		$regexp = '#^' . self::PATH_REGEXP . '$#';
		if (preg_match($regexp, $propertyPath, $matches) !== 1) {
			throw new InvalidArgumentException('Unsupported condition format.');
		}

		array_shift($matches); // whole expression

		/** @var string $source */
		$source = array_shift($matches);
		assert(count($matches) > 0);
		$tokens = explode('->', array_shift($matches));

		if ($source === '') {
			$source = null;
			if ($tokens[0] === 'this') {
				trigger_error("Using 'this->' is deprecated; use property traversing directly without 'this->'.", E_USER_DEPRECATED);
				array_shift($tokens);
			} elseif (str_contains($tokens[0], '\\')) {
				$source = array_shift($tokens);
				trigger_error("Using STI class prefix '$source->' is deprecated; use with double-colon '$source::'.", E_USER_DEPRECATED);
			}
		}

		if ($source !== null && !is_subclass_of($source, IEntity::class)) {
			throw new InvalidArgumentException("Property expression '$propertyPath' uses class '$source' that is not " . IEntity::class . '.');
		}

		return [$tokens, $source];
	}
}