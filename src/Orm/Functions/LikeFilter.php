<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm\Functions;

use Nette\Utils\Strings;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\Aggregator;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use function count, is_array, is_string;


class LikeFilter extends StepapoOrmFunction
{
	protected function processInDb(
		IPlatform $platform,
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args,
		?Aggregator $aggregator,
	): DbalExpressionResult
	{
		assert(count($args) === 2 && (is_string($args[0]) || (is_array($args[0]) && count($args[0]) > 0)) && is_string($args[1]));
		$parts = [];
		$values = [];
		$columns = [];
		foreach ((array) $args[0] as $col) {
			$parts[] = 'UNACCENT(LOWER(%column)) LIKE %_like_ OR LOWER(%column) LIKE %_like_';
			$column = $helper->processExpression($builder, $col, $aggregator);
			$columns[] = $column;
			$values[] = $column->args[0];
			$values[] = Strings::lower($args[1]);
			$values[] = $column->args[0];
			$values[] = Strings::lower($args[1]);
		}
		$expression = implode(' OR ', $parts);
		return $this->createDbalExpression($expression, $values, $columns, $aggregator);
	}
}
