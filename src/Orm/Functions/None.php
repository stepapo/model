<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm\Functions;

use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\Aggregator;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Stepapo\Utils\Orm\Has;


class None extends StepapoOrmFunction implements CallableFromProperty
{
	protected function processInDb(IPlatform $platform, DbalQueryBuilderHelper $helper, QueryBuilder $builder, array $args, ?Aggregator $aggregator): DbalExpressionResult
	{
		assert(count($args) === 2 && is_string($args[0]));
		return $helper->processExpression($builder, Has::none($args[0], $args[1]), $aggregator);
	}
}