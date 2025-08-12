<?php

namespace Stepapo\Model\Manipulation;

use App\Model\Orm;
use Nextras\Dbal\Platforms\Data\Fqn;
use Stepapo\Model\Manipulation\Config\Manipulation;
use Stepapo\Model\Manipulation\Config\ManipulationList;
use Stepapo\Model\Orm\PostProcessable;
use Stepapo\Utils\Printer;
use Stepapo\Utils\Service;


class Processor implements Service
{
	private ManipulationList $manipulationList;
	private Printer $printer;


	public function __construct(
		private Collector $collector,
		private Orm $orm,
	) {
		$this->printer = new Printer;
	}


	public function process(array $folders, array $groups): int
	{
		$start = microtime(true);
		$count = 0;
		$this->printer->printBigSeparator();
		$this->printer->printLine('Manipulations', 'aqua');
		$this->printer->printSeparator();
		try {
			$this->manipulationList = $this->collector->getManipulationList($folders);
			try {
				foreach ($this->manipulationList->manipulations as $iteration => $classes) {
					$classes = $this->sortClasses($classes, $groups);
					foreach ($classes as $forceUpdates) {
						foreach ($forceUpdates as $manipulation) {
							$group = $groups[$manipulation->class] ?? throw new \LogicException("ManipulationGroup for class '$manipulation->class' not defined.");
							$repository = $this->orm->getRepositoryByName($group->name . 'Repository');
							foreach ($manipulation->items as $itemName => $item) {
								$entity = $repository->getByData($item);
								if ($entity) {
									if (!$manipulation->forceUpdate) {
										continue;
									}
									if (!$entity->getData()->isSameAs($item)) {
										$result = $repository->createFromDataAndReturnResult($item, $entity, fromNeon: true);
										if ($result->isModified) {
											$this->printer->printText($repository->getMapper()->getTableName() instanceof Fqn ? $repository->getMapper()->getTableName()->getUnescaped() : $repository->getMapper()->getTableName(), 'white');
											$this->printer->printText(': updating item ');
											$this->printer->printText($itemName, 'white');
											if ($repository instanceof PostProcessable) {
												$repository->postProcessFromData($item, $entity, skipDefaults: true);
											}
											$count++;
											$this->printer->printOk();
											$this->printer->printDiff($result->modifiedValues);
										}
									}
								} else {
									$this->printer->printText($repository->getMapper()->getTableName() instanceof Fqn ? $repository->getMapper()->getTableName()->getUnescaped() : $repository->getMapper()->getTableName(), 'white');
									$this->printer->printText(': creating item ');
									$this->printer->printText($itemName, 'white');
									$entity = $repository->createFromData($item, fromNeon: true);
									if ($repository instanceof PostProcessable) {
										$repository->postProcessFromData($item, $entity);
									}
									$count++;
									$this->printer->printOk();
								}
							}
						}
					}
				}
				if ($count === 0) {
					$this->printer->printLine('No changes');
				}
				$this->printer->printSeparator();
				$this->orm->flush();
				$end = microtime(true);
				$this->printer->printLine(sprintf("%d items | %0.3f s | OK", $count, $end - $start), 'lime');
			} catch (\Exception $e) {
				$this->printer->printError();
				$this->printer->printSeparator();
				$end = microtime(true);
				$this->printer->printLine(sprintf("%d items | %0.3f s | ERROR in item '%s' of repository '%s'", $count, $end - $start, $itemName, $group->name), 'red');
				$this->printer->printLine($e->getMessage());
				$this->printer->printLine($e->getTraceAsString());
			}
		} catch (\Exception $e) {
			$end = microtime(true);
			$this->printer->printLine(sprintf("%d items | %0.3f s | ERROR", $count, $end - $start), 'red');
			$this->printer->printLine($e->getMessage());
			$this->printer->printLine($e->getTraceAsString());
		}
		return 0;
	}


	private function processManipulation(Manipulation $manipulation, array $groups): void
	{


	}


	/** @param ManipulationGroup[] $groups */
	private function sortClasses(array $classes, array $groups): array
	{
		$sortedClasses = [];
		$doneClasses = [];
		$doneGroups = [];
		while(count($classes) > count($sortedClasses)) {
			$resolvable = false;
			foreach ($classes as $name => $class) {
				if (isset($doneClasses[$name])) {
					continue;
				}
				$resolved = true;
				if ($groups[$name]->dependencies) {
					foreach ($groups[$name]->dependencies as $dependency) {
						$c = $this->getDependentGroupClass($dependency, $groups);
						if (!isset($doneGroups[$dependency]) && isset($classes[$c])) {
							$resolved = false;
							break;
						}
					}
				}
				if ($resolved) {
					$doneClasses[$name] = true;
					$doneGroups[$groups[$name]->name] = true;
					$sortedClasses[$name] = $class;
					$resolvable = true;
				}
			}
			if (!$resolvable) {
				throw new \LogicException("Order of class '$name' could not be resolved.");
			}
		}
		return $sortedClasses;
	}


	private function getDependentGroupClass(string $dependency, array $groups): string
	{
		foreach ($groups as $group) {
			if ($group->name === $dependency) {
				return $group->class;
			}
		}
		throw new \InvalidArgumentException();
	}
}
