<?php

declare (strict_types=1);
/*
 * This file is part of the league/commonmark package.
 *
 * (c) Colin O'Dell <colinodell@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace BlockFormatBridge\Vendor\League\CommonMark\Xml;

use BlockFormatBridge\Vendor\League\CommonMark\Node\Block\AbstractBlock;
use BlockFormatBridge\Vendor\League\CommonMark\Node\Inline\AbstractInline;
use BlockFormatBridge\Vendor\League\CommonMark\Node\Node;
/**
 * @internal
 */
final class FallbackNodeXmlRenderer implements XmlNodeRendererInterface {

	/**
	 * @var array<string, string>
	 *
	 * @psalm-allow-private-mutation
	 */
	private array $classCache = array();
	/**
	 * @psalm-allow-private-mutation
	 */
	public function getXmlTagName(Node $node): string {
		$className = \get_class($node);
		if ( isset($this->classCache[ $className ]) ) {
			return $this->classCache[ $className ];
		}
		$type                                  = $node instanceof AbstractBlock ? 'block' : 'inline';
		$shortName                             = \strtolower(( new \ReflectionClass($node) )->getShortName());
		return $this->classCache[ $className ] = \sprintf('custom_%s_%s', $type, $shortName);
	}
	/**
	 * {@inheritDoc}
	 */
	public function getXmlAttributes(Node $node): array {
		$attrs = array();
		foreach ( $node->data->export() as $k => $v ) {
			if ( self::isValueUsable($v) ) {
				$attrs[ $k ] = $v;
			}
		}
		$reflClass = new \ReflectionClass($node);
		foreach ( $reflClass->getProperties() as $property ) {
			if ( \in_array($property->getDeclaringClass()->getName(), array( Node::class, AbstractBlock::class, AbstractInline::class ), \true) ) {
				continue;
			}
			$property->setAccessible(\true);
			$value = $property->getValue($node);
			if ( self::isValueUsable($value) ) {
				$attrs[ $property->getName() ] = $value;
			}
		}
		return $attrs;
	}
	/**
	 * @param mixed $var
	 *
	 * @psalm-pure
	 */
	private static function isValueUsable($var_value): bool {
		return \is_string($var_value) || \is_int($var_value) || \is_float($var_value) || \is_bool($var_value);
	}
}
