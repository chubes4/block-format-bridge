<?php

declare (strict_types=1);
namespace BlockFormatBridge\Vendor\League\HTMLToMarkdown\Converter;

use BlockFormatBridge\Vendor\League\HTMLToMarkdown\Configuration;
use BlockFormatBridge\Vendor\League\HTMLToMarkdown\ConfigurationAwareInterface;
use BlockFormatBridge\Vendor\League\HTMLToMarkdown\ElementInterface;
class EmphasisConverter implements ConverterInterface, ConfigurationAwareInterface {

	/** @var Configuration */
	protected $config;
	protected function getNormTag(?ElementInterface $element): string {
		if ( null !== $element && ! $element->isText() ) {
			$tag = $element->getTagName();
			if ( 'i' === $tag || 'em' === $tag ) {
				return 'em';
			}
			if ( 'b' === $tag || 'strong' === $tag ) {
				return 'strong';
			}
		}
		return '';
	}
	public function setConfig(Configuration $config): void {
		$this->config = $config;
	}
	public function convert(ElementInterface $element): string {
		$tag   = $this->getNormTag($element);
		$value = $element->getValue();
		if ( ! \trim($value) ) {
			return $value;
		}
		if ( 'em' === $tag ) {
			$style = $this->config->getOption('italic_style');
		} else {
			$style = $this->config->getOption('bold_style');
		}
		$prefix = \ltrim($value) !== $value ? ' ' : '';
		$suffix = \rtrim($value) !== $value ? ' ' : '';
		/* If this node is immediately preceded or followed by one of the same type don't emit
		 * the start or end $style, respectively. This prevents <em>foo</em><em>bar</em> from
		 * being converted to *foo**bar* which is incorrect. We want *foobar* instead.
		 */
		$preStyle  = $this->getNormTag($element->getPreviousSibling()) === $tag ? '' : $style;
		$postStyle = $this->getNormTag($element->getNextSibling()) === $tag ? '' : $style;
		return $prefix . $preStyle . \trim($value) . $postStyle . $suffix;
	}
	/**
	 * @return string[]
	 */
	public function getSupportedTags(): array {
		return array( 'em', 'i', 'strong', 'b' );
	}
}
