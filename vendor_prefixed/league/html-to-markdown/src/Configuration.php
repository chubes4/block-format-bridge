<?php

declare (strict_types=1);
namespace BlockFormatBridge\Vendor\League\HTMLToMarkdown;

class Configuration {

	/** @var array<string, mixed> */
	protected $config;
	/**
	 * @param array<string, mixed> $config
	 */
	public function __construct(array $config = array()) {
		$this->config = $config;
		$this->checkForDeprecatedOptions($config);
	}
	/**
	 * @param array<string, mixed> $config
	 */
	public function merge(array $config = array()): void {
		$this->checkForDeprecatedOptions($config);
		$this->config = \array_replace_recursive($this->config, $config);
	}
	/**
	 * @param array<string, mixed> $config
	 */
	public function replace(array $config = array()): void {
		$this->checkForDeprecatedOptions($config);
		$this->config = $config;
	}
	/**
	 * @param mixed $value
	 */
	public function setOption(string $key, $value): void {
		$this->checkForDeprecatedOptions(array( $key => $value ));
		$this->config[ $key ] = $value;
	}
	/**
	 * @param mixed|null $default
	 *
	 * @return mixed|null
	 */
	public function getOption(?string $key = null, $default_value = null) {
		if ( null === $key ) {
			return $this->config;
		}
		if ( ! isset($this->config[ $key ]) ) {
			return $default_value;
		}
		return $this->config[ $key ];
	}
	/**
	 * @param array<string, mixed> $config
	 */
	private function checkForDeprecatedOptions(array $config): void {
		foreach ( $config as $key => $value ) {
			if ( 'bold_style' === $key && '**' !== $value ) {
				@\trigger_error('Customizing the bold_style option is deprecated and may be removed in the next major version', \E_USER_DEPRECATED);
			} elseif ( 'italic_style' === $key && '*' !== $value ) {
				@\trigger_error('Customizing the italic_style option is deprecated and may be removed in the next major version', \E_USER_DEPRECATED);
			}
		}
	}
}
