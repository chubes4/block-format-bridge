<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */
declare (strict_types=1);
namespace BlockFormatBridge\Vendor\Nette\Utils;

use BlockFormatBridge\Vendor\Nette;
use function explode, func_get_args, ini_get, is_array, is_callable, is_object, is_string, preg_replace, restore_error_handler, set_error_handler, sprintf, str_contains, str_ends_with;
/**
 * PHP callable tools.
 */
final class Callback {

	use Nette\StaticClass;

	/**
	 * Invokes internal PHP function with own error handler.
	 * @param  callable-string  $function
	 * @param  list<mixed>  $args
	 * @param  callable(string, int): (bool|void|null)  $onError
	 */
	public static function invokeSafe(string $function, array $args, callable $onError): mixed {
		$prev = set_error_handler(function (int $severity, string $message, string $file, int $line) use ($onError, &$prev, $function): bool {
			unset( $line );
			if ( $file === __FILE__ ) {
				$msg = ini_get('html_errors') ? Html::htmlToText($message) : $message;
				$msg = (string) preg_replace("#^{$function}\\(.*?\\): #", '', $msg);
				if ( $onError($msg, $severity) !== \false ) {
					return \true;
				}
			}
			return $prev ? $prev(...func_get_args()) !== \false : \false;
		});
		try {
			return $function(...$args);
		} finally {
			restore_error_handler();
		}
	}
	/**
	 * Checks that $callable is valid PHP callback. Otherwise throws exception. If the $syntax is set to true, only verifies
	 * that $callable has a valid structure to be used as a callback, but does not verify if the class or method actually exists.
	 * @return callable
	 * @throws Nette\InvalidArgumentException
	 */
	public static function check(mixed $callable_fn, bool $syntax = \false): mixed {
		if ( ! is_callable($callable_fn, $syntax) ) {
			throw new Nette\InvalidArgumentException($syntax ? 'Given value is not a callable type.' : sprintf("Callback '%s' is not callable.", self::toString($callable_fn)));
		}
		return $callable_fn;
	}
	/**
	 * Converts PHP callback to textual form. Class or method may not exists.
	 */
	public static function toString(mixed $callable_fn): string {
		if ( $callable_fn instanceof \Closure ) {
			$inner = self::unwrap($callable_fn);
			return '{closure' . ( $inner instanceof \Closure ? '}' : ' ' . self::toString($inner) . '}' );
		} else {
			is_callable(is_object($callable_fn) ? array( $callable_fn, '__invoke' ) : $callable_fn, \true, $textual);
			return $textual;
		}
	}
	/**
	 * Returns reflection for method or function used in PHP callback.
	 * @param  callable  $callable  type check is escalated to ReflectionException
	 * @throws \ReflectionException  if callback is not valid
	 */
	public static function toReflection(mixed $callable_fn): \ReflectionMethod|\ReflectionFunction {
		if ( $callable_fn instanceof \Closure ) {
			$callable_fn = self::unwrap($callable_fn);
		}
		if ( is_string($callable_fn) && str_contains($callable_fn, '::') ) {
			return new ReflectionMethod(...explode('::', $callable_fn, 2));
		} elseif ( is_array($callable_fn) ) {
			return new ReflectionMethod($callable_fn[0], $callable_fn[1]);
		} elseif ( is_object($callable_fn) && ! $callable_fn instanceof \Closure ) {
			return new ReflectionMethod($callable_fn, '__invoke');
		} else {
			assert($callable_fn instanceof \Closure || is_string($callable_fn));
			return new \ReflectionFunction($callable_fn);
		}
	}
	/**
	 * Checks whether PHP callback is function or static method.
	 */
	public static function isStatic(callable $callable_fn): bool {
		return is_string(is_array($callable_fn) ? $callable_fn[0] : $callable_fn);
	}
	/**
	 * Unwraps closure created by Closure::fromCallable().
	 * @return callable|array{object|class-string, string}|string
	 */
	public static function unwrap(\Closure $closure): callable|array|string {
		$r     = new \ReflectionFunction($closure);
		$class = $r->getClosureScopeClass()?->name;
		if ( str_ends_with($r->name, '}') ) {
			return $closure;
		} elseif ( ( $obj = $r->getClosureThis() ) && $obj::class === $class ) {
			return array( $obj, $r->name );
		} elseif ( $class ) {
			return array( $class, $r->name );
		} else {
			return $r->name;
		}
	}
}
