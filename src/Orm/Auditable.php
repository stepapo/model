<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm;

use Build\Model\Person\Person;
use DateTimeInterface;
use Webovac\Core\Model\ICmsEntity;


interface Auditable extends ICmsEntity
{
	function getCreatedByPerson(): ?Person;
	function getUpdatedByPerson(): ?Person;
	function getCreatedAt(): ?DateTimeInterface;
	function getUpdatedAt(): ?DateTimeInterface;
	function setCreatedByPerson(Person $person): self;
	function setUpdatedByPerson(Person $person): self;
	function setCreatedAt(DateTimeInterface $date): self;
	function setUpdatedAt(DateTimeInterface $date): self;
}