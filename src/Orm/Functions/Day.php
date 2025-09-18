<?php

namespace Stepapo\Model\Orm\Functions;

use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\Aggregator;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;


class Day extends StepapoOrmFunction implements Comparable
{
	protected function processInDb(IPlatform $platform, DbalQueryBuilderHelper $helper, QueryBuilder $builder, array $args, ?Aggregator $aggregator): DbalExpressionResult
	{
		assert(count($args) === 1 && is_string($args[0]));
		$column = $helper->processExpression($builder, $args[0], $aggregator);
		return $this->createDbalExpression(
			$platform->getName() === 'pgsql' ? 'EXTRACT(day FROM %column)' : 'DAY(%column)',
			[$column->args[0]],
			[$column],
			$aggregator,
		);
	}
}