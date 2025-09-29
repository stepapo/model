<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm\Functions;

use Nextras\Dbal\IConnection;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\Aggregator;
use Nextras\Orm\Collection\Functions\CollectionFunction;
use Nextras\Orm\Collection\Functions\Result\ArrayExpressionResult;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Entity\IEntity;
use Stepapo\Utils\Service;


abstract class StepapoOrmFunction implements Service, CollectionFunction
{
	public function __construct(
		private IConnection $connection,
	) {}


	public function processArrayExpression(
		ArrayCollectionHelper $helper,
		IEntity $entity,
		array $args,
		?Aggregator $aggregator = null,
	): ArrayExpressionResult
	{
		return new ArrayExpressionResult(null);
	}


	public function processDbalExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args,
		?Aggregator $aggregator = null,
	): DbalExpressionResult
	{
		return $this->processInDb($this->connection->getPlatform(), $helper, $builder, $args, $aggregator);
	}


	protected function createDbalExpression(string $expression, array $args, array $columns = [], ?Aggregator $aggregator = null)
	{
		return new DbalExpressionResult(
			expression: $expression,
			args: $args,
			joins: array_merge(...array_map(fn(DbalExpressionResult $result) => $result->joins, $columns)),
			groupBy: array_merge(...array_map(fn(DbalExpressionResult $result) => $result->groupBy, $columns)),
			aggregator: $aggregator,
		);
	}


	abstract protected function processInDb(IPlatform $platform, DbalQueryBuilderHelper $helper, QueryBuilder $builder, array $args, ?Aggregator $aggregator): DbalExpressionResult;
}