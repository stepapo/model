<?php

declare(strict_types=1);

namespace Stepapo\Model\Manipulation;

use App\Model\Web\WebData;
use Nette\InvalidArgumentException;
use Nette\Utils\FileInfo;
use Nette\Utils\Finder;
use Stepapo\Model\Manipulation\Config\Manipulation;
use Stepapo\Model\Manipulation\Config\ManipulationList;
use Stepapo\Utils\Service;
use Tracy\Dumper;


class Collector implements Service
{
	public function __construct(
		private array $parameters,
		private bool $debugMode,
		private bool $testMode,
	) {}


	public function getManipulationList(array $folders): ManipulationList
	{
		$configs = [];
		foreach ($folders as $folder) {
			$files = Finder::findFiles("*.neon")->from($folder)->sortByName();
			foreach ($files as $file) {
				$configs[] = Manipulation::neonToArray($file->getPathname(), $this->parameters);
			}
		}
		$list = [];
		$manipulationList = new ManipulationList;
		usort($configs, fn(array $a, array $b) => ($a['override'] ?? false) <=> ($b['override'] ?? false));
		foreach ($configs as $config) {
			$iteration = $config['iteration'] ?? 1;
			$class = $config['class'];
			$forceUpdate = $config['forceUpdate'] ?? true;
			$items = $config['items'] ?? [];
			$modes = $config['modes'] ?? ['prod', 'dev', 'test'];
			$override = $config['override'] ?? false;
			$prodMode = !$this->debugMode && !$this->testMode;
			if (
				($prodMode && !in_array('prod', $modes, true))
				|| ($this->debugMode && !in_array('dev', $modes, true))
				|| ($this->testMode && !in_array('test', $modes, true))
			) {
				continue;
			}
			if (isset($list[$iteration][$class][$forceUpdate])) {
				$list[$iteration][$class][$forceUpdate]['items'] = $this->mergeItems($class, $list[$iteration][$class][$forceUpdate]['items'], $items, $override);
			} else {
				$list[$iteration][$class][$forceUpdate] = $config;
			}
		}
		foreach ($list as $classes) {
			foreach ($classes as $class => $forceUpdates) {
				foreach ($forceUpdates as $config) {
					try {
						$manipulation = Manipulation::createFromArray($config, skipDefaults: $config['skipDefaults'] ?? false);
					} catch (\Exception $e) {
						throw new InvalidArgumentException("Error in class '$class': " . $e->getMessage());
					}
					$manipulationList->manipulations[$manipulation->iteration][$manipulation->class][$manipulation->forceUpdate] = $manipulation;
				}
			}
		}
		return $manipulationList;
	}


//	/** @param Manipulation[] $manipulations */
//	public function mergeManipulations(array $manipulations): Manipulation
//	{
//		foreach ($manipulations as $manipulation) {
//			if (!isset($mergedManipulation)) {
//				$mergedManipulation = $manipulation;
//				continue;
//			}
//			foreach ($manipulation->items as $name => $item) {
//				if (!isset($mergedManipulation->items[$name])) {
//					$mergedManipulation->items[$name] = $item;
//					continue;
//				}
//				$mergedManipulation->items[$name] = $this->mergeItems($manipulation->class, $name, $mergedManipulation->items[$name], $item);
//			}
//		}
//		return $mergedManipulation;
//	}


	public function mergeItems(string $class, array $one, array $two, bool $override): array
	{
		foreach ($two as $itemName => $data) {
			if (is_numeric($itemName)) {
				$one[] = $data;
			} elseif (is_array($data)) {
				$one[$itemName] = $this->mergeItem($class, $itemName, $one[$itemName] ?? [], $two[$itemName], $override);
			} elseif (isset($one[$itemName]) && $one[$itemName] !== $two[$itemName]) {
				if ($override) {
					$one[$itemName] = $two[$itemName];
				} else {
					throw new InvalidArgumentException("Unambiguous definition of item '$itemName' of class '$class'.");
				}
			}
		}
		return $one;
	}


	public function mergeItem(string $class, string $itemName, array $one, array $two, bool $override): array
	{
		foreach ($two as $valueName => $value) {
			if (is_array($value)) {
				$one[$valueName] = $this->mergeItem($class, (string) $valueName, $one[$valueName] ?? [], $two[$valueName], $override);
			} elseif (isset($one[$valueName]) && $one[$valueName] !== $two[$valueName]) {
				if ($override) {
					$one[$valueName] = $two[$valueName];
				} else {
					throw new InvalidArgumentException("Unambiguous definition of value '$valueName' in item '$itemName' of class '$class'.");
				}
			} else {
				$one[$valueName] = $value;
			}
		}
		return $one;
	}
}
