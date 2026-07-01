<?php

declare(strict_types=1);

namespace Stepapo\Model\Orm;

use Build\Model\Person\Person;
use DateTimeInterface;


trait AuditableTrait
{
	public function getCreatedByPerson(): ?Person
	{
		return $this->createdByPerson;
	}


	public function getUpdatedByPerson(): ?Person
	{
		return $this->updatedByPerson;
	}


	public function getCreatedAt(): ?DateTimeInterface
	{
		return $this->createdAt;
	}


	public function getUpdatedAt(): ?DateTimeInterface
	{
		return $this->updatedAt;
	}


	public function setCreatedByPerson(?Person $person): self
	{
		$this->createdByPerson = $person;
		return $this;
	}


	public function setUpdatedByPerson(?Person $person): self
	{
		$this->updatedByPerson = $person;
		return $this;
	}


	public function setCreatedAt(DateTimeInterface $date): self
	{
		$this->createdAt = $date;
		return $this;
	}


	public function setUpdatedAt(?DateTimeInterface $date): self
	{
		$this->updatedAt = $date;
		return $this;
	}
}
