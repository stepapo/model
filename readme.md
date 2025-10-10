# Model

Library that uses structured DB definitions to generate DDL and DML queries and Nextras ORM entity properties.

## Instalation

1. run composer

```bash
composer require stepapo/model
```

2. `config/config.neon`

```neon
extensions:
    stepapo.model: Stepapo\Model\DI\ModelExtension

stepapo.model:
    parameters: # array of parameters to use in various configurations,
    testMode: # true or false,
    driver: # mysql|pqsql,
    database: # default db schema,
    schemas: # array of schemas to work with
```

## Usage

### Definitions

Analyzes database schemas, tables and columns, compares to Definition configs and runs DDL queries if needed. Requires Nette DI and Nextras Dbal.

- `config/definitions/db.neon`

```neon
schemas:
    public:
        tables:
            person:
                columns:
                    id: [type: int, null: false, auto: true]
                    email: [type: string, null: false]
                    first_name: [type: string, null: false]
                    last_name: [type: string, null: false]
                    created_at: [type: datetime, null: false, default: now]
                    updated_at: [type: datetime, null: true]
                primaryKey: id
                uniqueKeys: [email]
```

- `bin/processDefinitions.php`

```php
App\Bootstrap::boot()
    ->createContainer()
    ->getByType(Stepapo\Model\Definition\DbProcessor::class)
    ->process([__DIR__ . '/../config/definitions']);
```

### Manipulations

Analyzes database rows, compares to Manipulation configs and runs DML queries if needed. Requires Nette DI and Nextras ORM.

- `Build\Model\Person\Person.php`

You can generate entity properties with [Webovac Generator](https://github.com/webovac/generator#entity-properties).

```php
namespace Build\Model\Person;

use Build\Model\Person\PersonData;

/**
 * @property int $id {primary}
 *
 * @property string $firstName
 * @property string $lastName
 * @property string|null $email
 *
 * @property DateTimeImmutable $createdAt {default now}
 * @property DateTimeImmutable|null $updatedAt
 * 
 * @method PersonData getData()
 */
class Person extends Stepapo\Model\Orm\StepapoEntity
{
    public function getDataClass(): string
    {
        return PersonData::class;
    }
}
```

- `Build\Model\Person\PersonData.php`

```php
namespace Build\Model\Person;

class PersonData extends Stepapo\Model\Data\Item
{
    public ?int $id;
    public string $email;
    public string $firstName;
    public string $lastName;
}
```

- `Build\Model\Person\PersonRepository.php`

```php
namespace Build\Model\Person;

class PersonRepository extends Stepapo\Model\Orm\StepapoRepository
{
}
```

- `config/manipulations/persons.neon`

```neon
class: Build\Model\Person\PersonData
items:
    admin:
        email: 'admin@test.test'
        firstName: admin
        lastName: admin
    user:
        email: 'user@test.test'
        firstName: user
        lastName: user
```

- `bin/processManipulations.php`

```php
App\Bootstrap::boot()
    ->createContainer()
    ->getByType(Stepapo\Model\Manipulation\Processor::class)
    ->process([__DIR__ . '/../config/manipulations']);
```
