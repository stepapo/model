<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm\Functions;

use Nette\InvalidArgumentException;
use Nette\Utils\Strings;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\Aggregator;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;


class ConcatFilter extends StepapoOrmFunction
{
	protected function processInDb(IPlatform $platform, DbalQueryBuilderHelper $helper, QueryBuilder $builder, array $args, ?Aggregator $aggregator): DbalExpressionResult
	{
		assert(count($args) === 2 && is_array($args[0]) && count($args[0]) > 0 && is_string($args[1]));
		$combinations = is_array($args[0][0]) ? $args[0] : [$args[0]];
		$parts = [];
		$values = [];
		$columns = [];
		foreach ($combinations as $cols) {
			$placeholders = [];
			$part = $platform->getName() === 'pgsql' ? 'LOWER(' : 'LOWER(CONCAT(';
			if (!is_array($cols)) {
				throw new InvalidArgumentException;
			}
			foreach ($cols as $col) {
				$placeholders[] = '%column';
				$column = $helper->processExpression($builder, $col, $aggregator);
				$values[] = $column->args[0];
				$columns[] = $column;
			}
			$values[] = Strings::lower($args[1]);
			$part .= implode($platform->getName() === 'pgsql' ? " || ' ' || " : ", ' ' , ", $placeholders) . ($platform->getName() === 'pgsql' ? ')' : '))') . ' LIKE %_like_';
			$parts[] = $part;
		}
		$expression = implode(' OR ', $parts);
		return $this->createDbalExpression($expression, $values, $columns, $aggregator);
	}
}