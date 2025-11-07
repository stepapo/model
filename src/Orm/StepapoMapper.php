<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm;

use Nextras\Orm\Mapper\Dbal\Conventions\Conventions;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use Webovac\Core\Model\CmsEntity;


abstract class StepapoMapper extends DbalMapper
{
	protected function createConventions(): IConventions
	{
		$conventions = parent::createConventions();
		assert($conventions instanceof Conventions);
		$conventions->manyHasManyStorageNamePattern = '%s2%s';
		return $conventions;
	}


	public function delete(StepapoEntity $entity): void
	{
		$this->connection->query('DELETE FROM %table WHERE id = %i', $this->getTableName(), $entity->getPersistedId());
	}
}