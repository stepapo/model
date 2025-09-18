<?php

namespace Stepapo\Model\Orm\Functions;

use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\Aggregator;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;


class ConcatSort extends StepapoOrmFunction
{
	protected function processInDb(IPlatform $platform, DbalQueryBuilderHelper $helper, QueryBuilder $builder, array $args, ?Aggregator $aggregator): DbalExpressionResult
	{
		assert(is_array($args) && count($args) > 0);
		$expression = $platform->getName() === 'pgsql' ? '' : 'CONCAT(';
		$placeholders = [];
		$values = [];
		$columns = [];
		foreach ($args as $col) {
			$placeholders[] = '%column';
			$column = $helper->processExpression($builder, $col, $aggregator);
			$values[] = $column->args[0];
			$columns[] = $column;
		}
		$expression .= implode($platform->getName() === 'pgsql' ? ' || ' : ', ', $placeholders) . ($platform->getName() === 'pgsql' ? '' : ')');
		return $this->createDbalExpression($expression, $values, $columns, $aggregator);
	}
}