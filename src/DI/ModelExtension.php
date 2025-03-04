<?php

declare(strict_types=1);

namespace Stepapo\Model\DI;

use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Stepapo\Model\Definition\MysqlAnalyzer;
use Stepapo\Model\Definition\MysqlProcessor;
use Stepapo\Model\Definition\PgsqlAnalyzer;
use Stepapo\Model\Definition\PgsqlProcessor;
use Stepapo\Model\Manipulation\Collector;
use Stepapo\Utils\DI\StepapoExtension;


class ModelExtension extends StepapoExtension
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'parameters' => Expect::array()->default([]),
			'testMode' => Expect::bool()->default(false),
			'driver' => Expect::string()->required(),
			'database' => Expect::string()->required(),
			'schemas' => Expect::arrayOf('string'),
		]);
	}


	public function loadConfiguration(): void
	{
		parent::loadConfiguration();
		$builder = $this->getContainerBuilder();
		$builder->addDefinition($this->prefix('definition.analyzer'))
			->setFactory($this->config->driver === 'pgsql' ? PgsqlAnalyzer::class : MysqlAnalyzer::class);
		$processor = $builder->addDefinition($this->prefix('definition.processor'))
			->setFactory($this->config->driver === 'pgsql' ? PgsqlProcessor::class : MysqlProcessor::class, [$this->config->schemas ?: ($this->config->driver === 'pgsql' ? ['public'] : [$this->config->database])]);
		if ($this->config->driver === 'mysql') {
			$processor->addSetup('setDefaultSchema', [$this->config->database]);
		}
		$builder->addDefinition($this->prefix('manipulation.collector'))
			->setFactory(Collector::class, [$this->config->parameters, $builder->parameters['debugMode'], $this->config->testMode]);
		$builder->addDefinition($this->prefix('definition.collector'))
			->setFactory(\Stepapo\Model\Definition\Collector::class, [
				['defaultSchema' => $this->config->driver === 'mysql' ? $this->config->database : 'public'],
			]);
		parent::loadConfiguration();
	}
}