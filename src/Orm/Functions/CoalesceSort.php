<?php

namespace Stepapo\Model\Orm\Functions;

use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\Aggregator;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;


class CoalesceSort extends StepapoOrmFunction
{
	protected function processInDb(IPlatform $platform, DbalQueryBuilderHelper $helper, QueryBuilder $builder, array $args, ?Aggregator $aggregator): DbalExpressionResult
	{
		assert(count($args) === 1 && is_array($args[0]) && count($args[0]) > 0);
		$expression = 'COALESCE(';
		$placeholders = [];
		$values = [];
		$columns = [];
		foreach ($args[0] as $col) {
			$placeholders[] = '%column';
			$column = $helper->processExpression($builder, $col, $aggregator);
			$values[] = $column->args[0];
			$columns[] = $column;
		}
		$expression .= implode(', ', $placeholders) . ')';
		return $this->reateDbalExpression($expression, $values, $columns, $aggregator);
	}
}