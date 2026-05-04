<?php

declare (strict_types=1);
namespace BlockFormatBridge\Vendor\League\HTMLToMarkdown\Converter;

use BlockFormatBridge\Vendor\League\HTMLToMarkdown\Coerce;
use BlockFormatBridge\Vendor\League\HTMLToMarkdown\Configuration;
use BlockFormatBridge\Vendor\League\HTMLToMarkdown\ConfigurationAwareInterface;
use BlockFormatBridge\Vendor\League\HTMLToMarkdown\ElementInterface;
use BlockFormatBridge\Vendor\League\HTMLToMarkdown\PreConverterInterface;
class TableConverter implements ConverterInterface, PreConverterInterface, ConfigurationAwareInterface {

	/** @var Configuration */
	protected $config;
	public function setConfig(Configuration $config): void {
		$this->config = $config;
	}
	/** @var array<string, string> */
	private static $alignments = array(
		'left'   => ':--',
		'right'  => '--:',
		'center' => ':-:',
	);
	/** @var array<int, string>|null */
	private $columnAlignments = array();
	/** @var string|null */
	private $caption = null;
	public function preConvert(ElementInterface $element): void {
		$tag = $element->getTagName();
		// Only table cells and caption are allowed to contain content.
		// Remove all text between other table elements.
		if ( 'th' === $tag || 'td' === $tag || 'caption' === $tag ) {
			return;
		}
		foreach ( $element->getChildren() as $child ) {
			if ( $child->isText() ) {
				$child->setFinalMarkdown('');
			}
		}
	}
	public function convert(ElementInterface $element): string {
		$value = $element->getValue();
		switch ( $element->getTagName() ) {
			case 'table':
				$this->columnAlignments = array();
				if ( $this->caption ) {
					$side = $this->config->getOption('table_caption_side');
					if ( 'top' === $side ) {
						$value = $this->caption . "\n" . $value;
					} elseif ( 'bottom' === $side ) {
						$value .= $this->caption;
					}
					$this->caption = null;
				}
				return $value . "\n";
			case 'caption':
				$this->caption = \trim($value);
				return '';
			case 'tr':
				$value .= "|\n";
				if ( null !== $this->columnAlignments ) {
					$value                 .= '|' . \implode('|', $this->columnAlignments) . "|\n";
					$this->columnAlignments = null;
				}
				return $value;
			case 'th':
			case 'td':
				if ( null !== $this->columnAlignments ) {
					$align                    = $element->getAttribute('align');
					$this->columnAlignments[] = self::$alignments[ $align ] ?? '---';
				}
				$value = \str_replace("\n", ' ', $value);
				$value = \str_replace('|', Coerce::toString($this->config->getOption('table_pipe_escape') ?? '\|'), $value);
				return '| ' . \trim($value) . ' ';
			case 'thead':
			case 'tbody':
			case 'tfoot':
			case 'colgroup':
			case 'col':
				return $value;
			default:
				return '';
		}
	}
	/**
	 * @return string[]
	 */
	public function getSupportedTags(): array {
		return array( 'table', 'tr', 'th', 'td', 'thead', 'tbody', 'tfoot', 'colgroup', 'col', 'caption' );
	}
}
