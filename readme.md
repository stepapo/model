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

### Entity Properties

Analyzes Nextras ORM entities, compares to Definition configs and updates entity properties if needed. Requires Nextras ORM.

- `config/definitions/db.neon`

See [Definitions](#definitions)

- `App\Model\Person\Person.php`

```php
namespace App\Model\Person;

class Person extends Stepapo\Model\Orm\StepapoEntity
{
}
```

- `bin/processEntities.php`

```php
$generator = new Stepapo\Generator\Generator;
$propertyProcessor = new PropertyProcessor($generator);
$propertyProcessor->process([__DIR__ . '/../config/definitions']);
```

- results in updated entity file

```php
namespace App\Model\Person;

/**
 * @property int $id {primary}
 *
 * @property string $firstName
 * @property string $lastName
 * @property string|null $email
 *
 * @property DateTimeImmutable $createdAt {default now}
 * @property DateTimeImmutable|null $updatedAt
 */
class Person extends Stepapo\Model\Orm\StepapoEntity
{
}
```

### Manipulations

Analyzes database rows, compares to Manipulation configs and runs DML queries if needed. Requires Nette DI and Nextras ORM.

- `App\Model\Person\Person.php`

```php
namespace App\Model\Person;

/**
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

Generate properties with [Entity Properties](#entity-properties).

- `App\Model\Person\PersonData.php`

```php
namespace App\Model\Person;

class PersonData extends Stepapo\Model\Data\Item
{
    public ?int $id;
    public string $email;
    public string $firstName;
    public string $lastName;
}
```

- `App\Model\Person\PersonRepository.php`

```php
namespace App\Model\Person;

class PersonRepository extends Stepapo\Model\Orm\StepapoRepository
{
}
```

- `config/manipulations/persons.neon`

```neon
class: App\Model\Person\PersonData
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
