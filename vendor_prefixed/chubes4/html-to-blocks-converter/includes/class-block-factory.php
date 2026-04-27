<?php

namespace BlockFormatBridge\Vendor;

/**
 * Block Factory - Creates block arrays compatible with serialize_blocks()
 *
 * Creates Gutenberg block structures from parsed HTML elements.
 * Works with HTML_To_Blocks_HTML_Element adapter for DOM-like access.
 */
if (!\defined('ABSPATH')) {
    exit;
}
class HTML_To_Blocks_Block_Factory
{
    /**
     * Creates a block array structure compatible with WordPress block parser format
     *
     * @param string $name         Block name (e.g., 'core/paragraph')
     * @param array  $attributes   Block attributes
     * @param array  $inner_blocks Nested block arrays
     * @return array Block array structure
     */
    public static function create_block($name, $attributes = [], $inner_blocks = [])
    {
        $registry = \WP_Block_Type_Registry::get_instance();
        if (!$registry->is_registered($name)) {
            return self::create_block('core/html', ['content' => '']);
        }
        $block_type = $registry->get_registered($name);
        $inner_html = '';
        $inner_content = [];
        if (!empty($inner_blocks)) {
            $html_parts = self::generate_wrapper_html($name, $attributes);
            $inner_html = $html_parts['opening'] . $html_parts['closing'];
            $inner_content[] = $html_parts['opening'];
            foreach ($inner_blocks as $index => $inner_block) {
                $inner_content[] = null;
            }
            $inner_content[] = $html_parts['closing'];
        } else {
            $block_html = self::generate_block_html($name, $attributes);
            if (!empty($block_html)) {
                $inner_html = $block_html;
                $inner_content[] = $block_html;
            }
        }
        $sanitized_attributes = self::sanitize_attributes($block_type, $attributes);
        return ['blockName' => $name, 'attrs' => $sanitized_attributes, 'innerBlocks' => $inner_blocks, 'innerHTML' => $inner_html, 'innerContent' => $inner_content];
    }
    /**
     * Generates the complete HTML for a block without inner blocks
     *
     * @param string $name       Block name
     * @param array  $attributes Block attributes
     * @return string Block HTML
     */
    private static function generate_block_html($name, $attributes)
    {
        switch ($name) {
            case 'core/paragraph':
                $content = $attributes['content'] ?? '';
                $align = '';
                if (!empty($attributes['align'])) {
                    $align = ' style="text-align: ' . esc_attr($attributes['align']) . '"';
                }
                return '<p' . $align . '>' . $content . '</p>';
            case 'core/heading':
                $level = $attributes['level'] ?? 2;
                $content = $attributes['content'] ?? '';
                $class = 'wp-block-heading';
                return "<h{$level} class=\"{$class}\">{$content}</h{$level}>";
            case 'core/list-item':
                $content = $attributes['content'] ?? '';
                return '<li>' . $content . '</li>';
            case 'core/button':
                return self::generate_button_html($attributes);
            case 'core/pullquote':
                return self::generate_pullquote_html($attributes);
            case 'core/verse':
                $content = $attributes['content'] ?? '';
                return '<pre class="wp-block-verse">' . $content . '</pre>';
            case 'core/image':
                return self::generate_image_html($attributes);
            case 'core/code':
                $content = esc_html($attributes['content'] ?? '');
                $extra_class = '';
                if (!empty($attributes['className']) && \strpos($attributes['className'], 'language-') !== \false) {
                    $extra_class = ' ' . esc_attr($attributes['className']);
                }
                return '<pre class="wp-block-code' . $extra_class . '"><code>' . $content . '</code></pre>';
            case 'core/preformatted':
                $content = $attributes['content'] ?? '';
                return '<pre class="wp-block-preformatted">' . $content . '</pre>';
            case 'core/separator':
                $class = 'wp-block-separator';
                if (!empty($attributes['className'])) {
                    $class .= ' ' . $attributes['className'];
                }
                return '<hr class="' . esc_attr($class) . '"/>';
            case 'core/table':
                return self::generate_table_html($attributes);
            case 'core/video':
                return self::generate_video_html($attributes);
            case 'core/audio':
                return self::generate_audio_html($attributes);
            case 'core/file':
                return self::generate_file_html($attributes);
            case 'core/embed':
                return self::generate_embed_html($attributes);
            default:
                return '';
        }
    }
    /**
     * Generates HTML for button block.
     *
     * @param array $attributes Block attributes.
     * @return string Button HTML.
     */
    private static function generate_button_html($attributes)
    {
        $text = $attributes['text'] ?? '';
        $url = $attributes['url'] ?? '';
        $rel = !empty($attributes['rel']) ? ' rel="' . esc_attr($attributes['rel']) . '"' : '';
        $target = !empty($attributes['linkTarget']) ? ' target="' . esc_attr($attributes['linkTarget']) . '"' : '';
        $class_name = 'wp-block-button';
        if (!empty($attributes['className'])) {
            $class_name .= ' ' . $attributes['className'];
        }
        return '<div class="' . esc_attr($class_name) . '"><a class="wp-block-button__link wp-element-button" href="' . esc_url($url) . '"' . $target . $rel . '>' . $text . '</a></div>';
    }
    /**
     * Generates HTML for pullquote block.
     *
     * @param array $attributes Block attributes.
     * @return string Pullquote HTML.
     */
    private static function generate_pullquote_html($attributes)
    {
        $value = $attributes['value'] ?? '';
        $citation = !empty($attributes['citation']) ? '<cite>' . $attributes['citation'] . '</cite>' : '';
        return '<figure class="wp-block-pullquote"><blockquote>' . $value . $citation . '</blockquote></figure>';
    }
    /**
     * Generates HTML for image block
     *
     * @param array $attributes Block attributes
     * @return string Image HTML
     */
    private static function generate_image_html($attributes)
    {
        $url = $attributes['url'] ?? '';
        if (empty($url)) {
            return '';
        }
        $alt = esc_attr($attributes['alt'] ?? '');
        $title = !empty($attributes['title']) ? ' title="' . esc_attr($attributes['title']) . '"' : '';
        $img = '<img src="' . esc_url($url) . '" alt="' . $alt . '"' . $title . '/>';
        if (!empty($attributes['href'])) {
            $rel = !empty($attributes['rel']) ? ' rel="' . esc_attr($attributes['rel']) . '"' : '';
            $img = '<a href="' . esc_url($attributes['href']) . '"' . $rel . '>' . $img . '</a>';
        }
        $figcaption = '';
        if (!empty($attributes['caption'])) {
            $figcaption = '<figcaption class="wp-element-caption">' . $attributes['caption'] . '</figcaption>';
        }
        $class = 'wp-block-image';
        if (!empty($attributes['align'])) {
            $class .= ' align' . $attributes['align'];
        }
        return '<figure class="' . esc_attr($class) . '">' . $img . $figcaption . '</figure>';
    }
    /**
     * Generates HTML for table block
     *
     * @param array $attributes Block attributes
     * @return string Table HTML
     */
    private static function generate_table_html($attributes)
    {
        $html = '<figure class="wp-block-table"><table>';
        if (!empty($attributes['head'])) {
            $html .= '<thead>';
            foreach ($attributes['head'] as $row) {
                $html .= '<tr>';
                foreach ($row['cells'] ?? [] as $cell) {
                    $tag = $cell['tag'] ?? 'th';
                    $html .= "<{$tag}>" . ($cell['content'] ?? '') . "</{$tag}>";
                }
                $html .= '</tr>';
            }
            $html .= '</thead>';
        }
        if (!empty($attributes['body'])) {
            $html .= '<tbody>';
            foreach ($attributes['body'] as $row) {
                $html .= '<tr>';
                foreach ($row['cells'] ?? [] as $cell) {
                    $tag = $cell['tag'] ?? 'td';
                    $html .= "<{$tag}>" . ($cell['content'] ?? '') . "</{$tag}>";
                }
                $html .= '</tr>';
            }
            $html .= '</tbody>';
        }
        if (!empty($attributes['foot'])) {
            $html .= '<tfoot>';
            foreach ($attributes['foot'] as $row) {
                $html .= '<tr>';
                foreach ($row['cells'] ?? [] as $cell) {
                    $tag = $cell['tag'] ?? 'td';
                    $html .= "<{$tag}>" . ($cell['content'] ?? '') . "</{$tag}>";
                }
                $html .= '</tr>';
            }
            $html .= '</tfoot>';
        }
        $html .= '</table>';
        if (!empty($attributes['caption'])) {
            $html .= '<figcaption class="wp-element-caption">' . $attributes['caption'] . '</figcaption>';
        }
        $html .= '</figure>';
        return $html;
    }
    /**
     * Generates HTML for a video block.
     *
     * @param array $attributes Block attributes.
     * @return string Block HTML.
     */
    private static function generate_video_html($attributes)
    {
        $src = $attributes['src'] ?? '';
        if ($src === '') {
            return '';
        }
        $attrs = ' controls';
        foreach (['autoplay', 'loop', 'muted', 'playsInline'] as $flag) {
            if (!empty($attributes[$flag])) {
                $attrs .= ' ' . \strtolower($flag === 'playsInline' ? 'playsinline' : $flag);
            }
        }
        foreach (['poster', 'preload'] as $key) {
            if (!empty($attributes[$key])) {
                $attrs .= ' ' . $key . '="' . esc_attr($attributes[$key]) . '"';
            }
        }
        $html = '<figure class="wp-block-video"><video src="' . esc_url($src) . '"' . $attrs . '></video>';
        if (!empty($attributes['caption'])) {
            $html .= '<figcaption class="wp-element-caption">' . $attributes['caption'] . '</figcaption>';
        }
        $html .= '</figure>';
        return $html;
    }
    /**
     * Generates HTML for an audio block.
     *
     * @param array $attributes Block attributes.
     * @return string Block HTML.
     */
    private static function generate_audio_html($attributes)
    {
        $src = $attributes['src'] ?? '';
        if ($src === '') {
            return '';
        }
        $attrs = ' controls';
        foreach (['autoplay', 'loop'] as $flag) {
            if (!empty($attributes[$flag])) {
                $attrs .= ' ' . $flag;
            }
        }
        if (!empty($attributes['preload'])) {
            $attrs .= ' preload="' . esc_attr($attributes['preload']) . '"';
        }
        $html = '<figure class="wp-block-audio"><audio src="' . esc_url($src) . '"' . $attrs . '></audio>';
        if (!empty($attributes['caption'])) {
            $html .= '<figcaption class="wp-element-caption">' . $attributes['caption'] . '</figcaption>';
        }
        $html .= '</figure>';
        return $html;
    }
    /**
     * Generates HTML for a file block.
     *
     * @param array $attributes Block attributes.
     * @return string Block HTML.
     */
    private static function generate_file_html($attributes)
    {
        $href = $attributes['href'] ?? $attributes['textLinkHref'] ?? '';
        if ($href === '') {
            return '';
        }
        $name = $attributes['fileName'] ?? \basename(\strtok($href, '?#'));
        $target = !empty($attributes['textLinkTarget']) ? ' target="' . esc_attr($attributes['textLinkTarget']) . '"' : '';
        $html = '<div class="wp-block-file"><a href="' . esc_url($href) . '"' . $target . '>' . $name . '</a>';
        if (!isset($attributes['showDownloadButton']) || $attributes['showDownloadButton']) {
            $html .= '<a href="' . esc_url($href) . '" class="wp-block-file__button wp-element-button" download>Download</a>';
        }
        $html .= '</div>';
        return $html;
    }
    /**
     * Generates HTML for an embed block.
     *
     * @param array $attributes Block attributes.
     * @return string Block HTML.
     */
    private static function generate_embed_html($attributes)
    {
        $url = $attributes['url'] ?? '';
        if ($url === '') {
            return '';
        }
        $provider = $attributes['providerNameSlug'] ?? '';
        $class = 'wp-block-embed';
        if ($provider !== '') {
            $class .= ' is-provider-' . sanitize_html_class($provider) . ' wp-block-embed-' . sanitize_html_class($provider);
        }
        return '<figure class="' . esc_attr($class) . '"><div class="wp-block-embed__wrapper">' . esc_url($url) . '</div></figure>';
    }
    /**
     * Generates wrapper HTML for blocks with inner blocks
     *
     * @param string $name       Block name
     * @param array  $attributes Block attributes
     * @return array Opening and closing HTML tags
     */
    private static function generate_wrapper_html($name, $attributes)
    {
        switch ($name) {
            case 'core/list':
                $tag = !empty($attributes['ordered']) ? 'ol' : 'ul';
                $class = 'wp-block-list';
                return ['opening' => "<{$tag} class=\"{$class}\">", 'closing' => "</{$tag}>"];
            case 'core/list-item':
                $content = $attributes['content'] ?? '';
                return ['opening' => '<li>' . $content, 'closing' => '</li>'];
            case 'core/quote':
                return ['opening' => '<blockquote class="wp-block-quote">', 'closing' => '</blockquote>'];
            case 'core/buttons':
                return ['opening' => '<div class="wp-block-buttons">', 'closing' => '</div>'];
            case 'core/details':
                $summary = $attributes['summary'] ?? '';
                return ['opening' => '<details class="wp-block-details"><summary>' . $summary . '</summary>', 'closing' => '</details>'];
            case 'core/group':
                return ['opening' => '<div class="wp-block-group">', 'closing' => '</div>'];
            case 'core/column':
                return ['opening' => '<div class="wp-block-column">', 'closing' => '</div>'];
            case 'core/columns':
                return ['opening' => '<div class="wp-block-columns">', 'closing' => '</div>'];
            case 'core/gallery':
                $class = 'wp-block-gallery has-nested-images columns-default is-cropped';
                if (!empty($attributes['columns'])) {
                    $class = 'wp-block-gallery has-nested-images columns-' . (int) $attributes['columns'] . ' is-cropped';
                }
                return ['opening' => '<figure class="' . esc_attr($class) . '">', 'closing' => '</figure>'];
            case 'core/media-text':
                $media_url = $attributes['mediaUrl'] ?? '';
                $media_type = $attributes['mediaType'] ?? 'image';
                $media_alt = esc_attr($attributes['mediaAlt'] ?? '');
                $media_html = $media_type === 'video' ? '<video src="' . esc_url($media_url) . '" controls></video>' : '<img src="' . esc_url($media_url) . '" alt="' . $media_alt . '"/>';
                $class = 'wp-block-media-text is-stacked-on-mobile';
                if (($attributes['mediaPosition'] ?? 'left') === 'right') {
                    $class .= ' has-media-on-the-right';
                }
                return ['opening' => '<div class="' . esc_attr($class) . '"><figure class="wp-block-media-text__media">' . $media_html . '</figure><div class="wp-block-media-text__content">', 'closing' => '</div></div>'];
            default:
                return ['opening' => '', 'closing' => ''];
        }
    }
    /**
     * Sanitizes block attributes against the block type schema
     * Excludes attributes with source types (rich-text, html, text) as those are derived from HTML
     *
     * @param WP_Block_Type $block_type Block type object
     * @param array         $attributes Raw attributes
     * @return array Sanitized attributes for JSON serialization
     */
    private static function sanitize_attributes($block_type, $attributes)
    {
        if (empty($block_type->attributes)) {
            return $attributes;
        }
        $sanitized = [];
        foreach ($attributes as $key => $value) {
            if (!isset($block_type->attributes[$key])) {
                continue;
            }
            $schema = $block_type->attributes[$key];
            if (isset($schema['source'])) {
                continue;
            }
            $type = $schema['type'] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            if ($type === 'rich-text') {
                continue;
            }
            switch ($type) {
                case 'string':
                    $sanitized[$key] = (string) $value;
                    break;
                case 'number':
                case 'integer':
                    $sanitized[$key] = \is_numeric($value) ? (int) $value : null;
                    break;
                case 'boolean':
                    $sanitized[$key] = (bool) $value;
                    break;
                case 'array':
                    $sanitized[$key] = \is_array($value) ? $value : [$value];
                    break;
                case 'object':
                    $sanitized[$key] = \is_array($value) ? $value : [];
                    break;
                default:
                    $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
    /**
     * Gets the inner HTML content from an element
     *
     * @param HTML_To_Blocks_HTML_Element|string $element Element or HTML string
     * @return string Inner HTML
     */
    public static function get_inner_html($element)
    {
        if ($element instanceof HTML_To_Blocks_HTML_Element) {
            return $element->get_inner_html();
        }
        if (\is_string($element)) {
            $parsed = HTML_To_Blocks_HTML_Element::from_html($element);
            return $parsed ? $parsed->get_inner_html() : '';
        }
        return '';
    }
    /**
     * Gets text content from an element
     *
     * @param HTML_To_Blocks_HTML_Element|string $element Element or HTML string
     * @return string Text content
     */
    public static function get_text_content($element)
    {
        if ($element instanceof HTML_To_Blocks_HTML_Element) {
            return $element->get_text_content();
        }
        if (\is_string($element)) {
            $parsed = HTML_To_Blocks_HTML_Element::from_html($element);
            return $parsed ? $parsed->get_text_content() : '';
        }
        return '';
    }
}
