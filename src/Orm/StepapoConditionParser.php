<?php

namespace Stepapo\Model\Orm;

use App\Lib\OrmFunctions;
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


class StepapoConditionParser extends ConditionParser
{
	// language=PhpRegExp
	protected const PATH_REGEXP = '(?:([\w\\\]+)::)?([\w\\\]++(?:->\w++)*+)';


	public function parsePropertyOperator(string $condition): array
	{
		// language=PhpRegExp
		$regexp = '#^(?P<path>' . self::PATH_REGEXP . ')(:(?P<function>[a-zA-Z]+))?(?P<operator>!=|<=|>=|=|>|<|~)?$#';
		if (preg_match($regexp, $condition, $matches) !== 1) {
			return [CompareEqualsFunction::class, $condition];
		}
		$useOperator = true;
		$operator = $matches['operator'] ?? '=';
		$path = $matches['path'];
		$function = $matches['function'] ?? null;
		if ($function) {
			$path = match ($function) {
				'avg' => [AvgAggregateFunction::class, $path],
				'count' => [CountAggregateFunction::class, $path],
				'max' => [MaxAggregateFunction::class, $path],
				'min' => [MinAggregateFunction::class, $path],
				'sum' => [SumAggregateFunction::class, $path],
				default => [$function, $path],
			};
			if (!in_array($function, ['avg', 'count', 'max', 'min', 'sum', 'year', 'month', 'day', 'date'], true)) {
				$useOperator = false;
			}
		}

		return $useOperator ? match ($operator) {
			'=' => [CompareEqualsFunction::class, $path],
			'!=' => [CompareNotEqualsFunction::class, $path],
			'>=' => [CompareGreaterThanEqualsFunction::class, $path],
			'>' => [CompareGreaterThanFunction::class, $path],
			'<=' => [CompareSmallerThanEqualsFunction::class, $path],
			'<' => [CompareSmallerThanFunction::class, $path],
			'~'	=> [CompareLikeFunction::class, $path],
			default => throw new InvalidStateException,
		} : $path;
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