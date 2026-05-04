<?php

declare (strict_types=1);
/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */
namespace BlockFormatBridge\Vendor\Nette\Schema;

use function count;
final class Context {

	public bool $skipDefaults = \false;
	/** @var list<int|string> */
	public array $path = array();
	public bool $isKey = \false;
	/** @var list<Message> */
	public array $errors = array();
	/** @var list<Message> */
	public array $warnings = array();
	/** @var list<array{DynamicParameter, string, list<int|string>}> */
	public array $dynamics = array();
	/** @param  array<string, mixed>  $variables */
	public function addError(string $message, string $code, array $variables = array()): Message {
		$variables['isKey']    = $this->isKey;
		return $this->errors[] = new Message($message, $code, $this->path, $variables);
	}
	/** @param  array<string, mixed>  $variables */
	public function addWarning(string $message, string $code, array $variables = array()): Message {
		return $this->warnings[] = new Message($message, $code, $this->path, $variables);
	}
	/** @return \Closure(): bool */
	public function createChecker(): \Closure {
		$count = count($this->errors);
		return fn(): bool => $count === count($this->errors);
	}
}
