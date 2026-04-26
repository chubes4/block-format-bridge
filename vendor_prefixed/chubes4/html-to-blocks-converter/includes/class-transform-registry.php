<?php

namespace BlockFormatBridge\Vendor;

/**
 * Transform Registry - PHP raw transforms mirroring Gutenberg JS transforms
 *
 * Uses HTML_To_Blocks_HTML_Element adapter for DOM-like access via WordPress HTML API.
 * Only type raw transforms for server-side HTML-to-blocks conversion.
 */
if (!\defined('ABSPATH')) {
    exit;
}
class HTML_To_Blocks_Transform_Registry
{
    private static $transforms = null;
    /**
     * Gets all raw transforms for core blocks
     * Sorted by priority (lower = higher priority)
     *
     * @return array Array of transform definitions
     */
    public static function get_raw_transforms()
    {
        if (self::$transforms !== null) {
            return self::$transforms;
        }
        self::$transforms = \array_merge(self::get_heading_transforms(), self::get_list_transforms(), self::get_image_transforms(), self::get_quote_transforms(), self::get_code_transforms(), self::get_preformatted_transforms(), self::get_separator_transforms(), self::get_table_transforms(), self::get_paragraph_transforms());
        \usort(self::$transforms, function ($a, $b) {
            return ($a['priority'] ?? 10) - ($b['priority'] ?? 10);
        });
        return self::$transforms;
    }
    /**
     * core/heading transforms - h1-h6 elements
     *
     * @return array Transform definitions
     */
    private static function get_heading_transforms()
    {
        return [['blockName' => 'core/heading', 'priority' => 10, 'selector' => 'h1,h2,h3,h4,h5,h6', 'isMatch' => function ($element) {
            return \preg_match('/^H[1-6]$/i', $element->get_tag_name());
        }, 'transform' => function ($element) {
            $level = (int) \substr($element->get_tag_name(), 1);
            $content = $element->get_inner_html();
            $attributes = ['level' => $level, 'content' => $content];
            if ($element->has_attribute('id')) {
                $attributes['anchor'] = $element->get_attribute('id');
            }
            if ($element->has_attribute('style')) {
                $style = $element->get_attribute('style');
                if (\preg_match('/text-align:\s*(left|center|right)/i', $style, $matches)) {
                    $attributes['textAlign'] = \strtolower($matches[1]);
                }
            }
            return HTML_To_Blocks_Block_Factory::create_block('core/heading', $attributes);
        }]];
    }
    /**
     * core/list transforms - ol and ul elements
     *
     * @return array Transform definitions
     */
    private static function get_list_transforms()
    {
        return [['blockName' => 'core/list', 'priority' => 10, 'selector' => 'ol,ul', 'isMatch' => function ($element) {
            return \in_array($element->get_tag_name(), ['OL', 'UL'], \true);
        }, 'transform' => function ($element) {
            return self::create_list_block_from_element($element);
        }]];
    }
    /**
     * Creates a list block from an HTML element (recursive for nested lists)
     *
     * @param HTML_To_Blocks_HTML_Element $list_element The ol/ul element
     * @return array Block array
     */
    private static function create_list_block_from_element($list_element)
    {
        $ordered = $list_element->get_tag_name() === 'OL';
        $list_attributes = ['ordered' => $ordered];
        if ($list_element->has_attribute('id') && $list_element->get_attribute('id') !== '') {
            $list_attributes['anchor'] = $list_element->get_attribute('id');
        }
        if ($list_element->has_attribute('start')) {
            $list_attributes['start'] = (int) $list_element->get_attribute('start');
        }
        if ($list_element->has_attribute('reversed')) {
            $list_attributes['reversed'] = \true;
        }
        if ($list_element->has_attribute('type')) {
            $type = $list_element->get_attribute('type');
            $type_map = ['A' => 'upper-alpha', 'a' => 'lower-alpha', 'I' => 'upper-roman', 'i' => 'lower-roman'];
            if (isset($type_map[$type])) {
                $list_attributes['type'] = $type_map[$type];
            }
        }
        $inner_blocks = [];
        $li_elements = self::get_direct_li_children($list_element->get_inner_html());
        foreach ($li_elements as $li_html) {
            $li = HTML_To_Blocks_HTML_Element::from_html($li_html);
            if ($li) {
                $list_item_block = self::create_list_item_block($li);
                if ($list_item_block) {
                    $inner_blocks[] = $list_item_block;
                }
            }
        }
        return HTML_To_Blocks_Block_Factory::create_block('core/list', $list_attributes, $inner_blocks);
    }
    /**
     * Creates a list-item block from an li element
     *
     * @param HTML_To_Blocks_HTML_Element $li_element The li element
     * @return array Block array
     */
    private static function create_list_item_block($li_element)
    {
        $inner_html = $li_element->get_inner_html();
        $nested_list = null;
        $nested_ol = $li_element->query_selector('ol');
        $nested_ul = $li_element->query_selector('ul');
        if ($nested_ol) {
            $nested_list = $nested_ol;
            $inner_html = \preg_replace('/<ol[^>]*>.*<\/ol>/is', '', $inner_html);
        } elseif ($nested_ul) {
            $nested_list = $nested_ul;
            $inner_html = \preg_replace('/<ul[^>]*>.*<\/ul>/is', '', $inner_html);
        }
        $content = \trim($inner_html);
        $inner_blocks = [];
        if ($nested_list) {
            $inner_blocks[] = self::create_list_block_from_element($nested_list);
        }
        return HTML_To_Blocks_Block_Factory::create_block('core/list-item', ['content' => $content], $inner_blocks);
    }
    /**
     * Gets direct <li> children from list inner HTML
     *
     * @param string $inner_html The inner HTML of an ol/ul element
     * @return array Array of li element HTML strings
     */
    private static function get_direct_li_children(string $inner_html): array
    {
        $results = [];
        $len = \strlen($inner_html);
        $i = 0;
        $list_depth = 0;
        while ($i < $len) {
            $remaining = \substr($inner_html, $i);
            if (\preg_match('/^<(ul|ol)(?:\s|>)/i', $remaining)) {
                $list_depth++;
                $i++;
                continue;
            }
            if (\preg_match('/^<\/(ul|ol)\s*>/i', $remaining)) {
                $list_depth--;
                $i++;
                continue;
            }
            if ($list_depth === 0 && \preg_match('/^<li(?:\s[^>]*)?>/i', $remaining)) {
                $li_html = self::extract_balanced_li($remaining);
                if ($li_html) {
                    $results[] = $li_html;
                    $i += \strlen($li_html);
                    continue;
                }
            }
            $i++;
        }
        return $results;
    }
    /**
     * Extracts a balanced <li> element including nested lists
     *
     * @param string $html HTML starting with <li
     * @return string|null Complete li element or null
     */
    private static function extract_balanced_li(string $html): ?string
    {
        $li_depth = 0;
        $len = \strlen($html);
        $i = 0;
        while ($i < $len) {
            $remaining = \substr($html, $i);
            if (\preg_match('/^<li(?:\s|>)/i', $remaining)) {
                $li_depth++;
            } elseif (\preg_match('/^<\/li\s*>/i', $remaining, $close_match)) {
                $li_depth--;
                if ($li_depth === 0) {
                    return \substr($html, 0, $i + \strlen($close_match[0]));
                }
            }
            $i++;
        }
        return null;
    }
    /**
     * core/image transforms - figure with img
     *
     * @return array Transform definitions
     */
    private static function get_image_transforms()
    {
        return [['blockName' => 'core/image', 'priority' => 10, 'isMatch' => function ($element) {
            if ($element->get_tag_name() !== 'FIGURE') {
                return \false;
            }
            $img = $element->query_selector('img');
            return $img !== null;
        }, 'transform' => function ($element) {
            $img = $element->query_selector('img');
            $figcaption = $element->query_selector('figcaption');
            $attributes = ['url' => $img->get_attribute('src') ?? ''];
            if ($img->has_attribute('alt')) {
                $attributes['alt'] = $img->get_attribute('alt');
            }
            if ($img->has_attribute('title')) {
                $attributes['title'] = $img->get_attribute('title');
            }
            if ($figcaption) {
                $attributes['caption'] = $figcaption->get_inner_html();
            }
            $class_name = '';
            if ($element->has_attribute('class')) {
                $class_name .= $element->get_attribute('class') . ' ';
            }
            if ($img->has_attribute('class')) {
                $class_name .= $img->get_attribute('class');
            }
            $class_name = \trim($class_name);
            if (\preg_match('/(?:^|\s)align(left|center|right)(?:$|\s)/', $class_name, $matches)) {
                $attributes['align'] = $matches[1];
            }
            if (\preg_match('/(?:^|\s)wp-image-(\d+)(?:$|\s)/', $class_name, $matches)) {
                $attributes['id'] = (int) $matches[1];
            }
            if ($element->has_attribute('id') && $element->get_attribute('id') !== '') {
                $attributes['anchor'] = $element->get_attribute('id');
            }
            $anchor_element = $element->query_selector('a');
            if ($anchor_element && $anchor_element->has_attribute('href')) {
                $attributes['href'] = $anchor_element->get_attribute('href');
                $attributes['linkDestination'] = 'custom';
                if ($anchor_element->has_attribute('rel')) {
                    $attributes['rel'] = $anchor_element->get_attribute('rel');
                }
                if ($anchor_element->has_attribute('class')) {
                    $attributes['linkClass'] = $anchor_element->get_attribute('class');
                }
            }
            return HTML_To_Blocks_Block_Factory::create_block('core/image', $attributes);
        }], ['blockName' => 'core/image', 'priority' => 15, 'isMatch' => function ($element) {
            return $element->get_tag_name() === 'IMG';
        }, 'transform' => function ($element) {
            $attributes = ['url' => $element->get_attribute('src') ?? ''];
            if ($element->has_attribute('alt')) {
                $attributes['alt'] = $element->get_attribute('alt');
            }
            if ($element->has_attribute('title')) {
                $attributes['title'] = $element->get_attribute('title');
            }
            $class_name = $element->has_attribute('class') ? $element->get_attribute('class') : '';
            if (\preg_match('/(?:^|\s)align(left|center|right)(?:$|\s)/', $class_name, $matches)) {
                $attributes['align'] = $matches[1];
            }
            if (\preg_match('/(?:^|\s)wp-image-(\d+)(?:$|\s)/', $class_name, $matches)) {
                $attributes['id'] = (int) $matches[1];
            }
            return HTML_To_Blocks_Block_Factory::create_block('core/image', $attributes);
        }]];
    }
    /**
     * core/quote transforms - blockquote elements
     *
     * @return array Transform definitions
     */
    private static function get_quote_transforms()
    {
        return [['blockName' => 'core/quote', 'priority' => 10, 'selector' => 'blockquote', 'isMatch' => function ($element) {
            return $element->get_tag_name() === 'BLOCKQUOTE';
        }, 'transform' => function ($element, $handler) {
            $inner_html = $element->get_inner_html();
            $inner_blocks = $handler(['HTML' => $inner_html]);
            $attributes = [];
            if ($element->has_attribute('id') && $element->get_attribute('id') !== '') {
                $attributes['anchor'] = $element->get_attribute('id');
            }
            return HTML_To_Blocks_Block_Factory::create_block('core/quote', $attributes, $inner_blocks);
        }]];
    }
    /**
     * core/code transforms - pre > code elements
     *
     * @return array Transform definitions
     */
    private static function get_code_transforms()
    {
        return [['blockName' => 'core/code', 'priority' => 10, 'isMatch' => function ($element) {
            if ($element->get_tag_name() !== 'PRE') {
                return \false;
            }
            $code = $element->query_selector('code');
            if (!$code) {
                return \false;
            }
            $inner_html = $element->get_inner_html();
            $stripped = \preg_replace('/<code[^>]*>.*<\/code>/is', '', $inner_html);
            $has_only_code = empty(\trim(\strip_tags($stripped)));
            return $has_only_code;
        }, 'transform' => function ($element) {
            $code = $element->query_selector('code');
            $content = $code ? $code->get_text_content() : $element->get_text_content();
            $attributes = ['content' => $content];
            // Preserve language class for syntax highlighting
            if ($code && $code->has_attribute('class')) {
                $class = $code->get_attribute('class');
                if (\preg_match('/language-(\S+)/', $class, $matches)) {
                    $attributes['className'] = 'language-' . $matches[1];
                }
            }
            return HTML_To_Blocks_Block_Factory::create_block('core/code', $attributes);
        }]];
    }
    /**
     * core/preformatted transforms - pre elements (not containing code)
     *
     * @return array Transform definitions
     */
    private static function get_preformatted_transforms()
    {
        return [['blockName' => 'core/preformatted', 'priority' => 11, 'isMatch' => function ($element) {
            if ($element->get_tag_name() !== 'PRE') {
                return \false;
            }
            $code = $element->query_selector('code');
            if (!$code) {
                return \true;
            }
            $inner_html = $element->get_inner_html();
            $stripped = \preg_replace('/<code[^>]*>.*<\/code>/is', '', $inner_html);
            $has_only_code = empty(\trim(\strip_tags($stripped)));
            return !$has_only_code;
        }, 'transform' => function ($element) {
            $content = $element->get_inner_html();
            $attributes = ['content' => $content];
            if ($element->has_attribute('id') && $element->get_attribute('id') !== '') {
                $attributes['anchor'] = $element->get_attribute('id');
            }
            return HTML_To_Blocks_Block_Factory::create_block('core/preformatted', $attributes);
        }]];
    }
    /**
     * core/separator transforms - hr elements
     *
     * @return array Transform definitions
     */
    private static function get_separator_transforms()
    {
        return [['blockName' => 'core/separator', 'priority' => 10, 'selector' => 'hr', 'isMatch' => function ($element) {
            return $element->get_tag_name() === 'HR';
        }, 'transform' => function ($element) {
            $attributes = [];
            if ($element->has_attribute('class')) {
                $class = $element->get_attribute('class');
                if (\strpos($class, 'is-style-wide') !== \false) {
                    $attributes['className'] = 'is-style-wide';
                } elseif (\strpos($class, 'is-style-dots') !== \false) {
                    $attributes['className'] = 'is-style-dots';
                }
            }
            return HTML_To_Blocks_Block_Factory::create_block('core/separator', $attributes);
        }]];
    }
    /**
     * core/table transforms - table elements
     *
     * @return array Transform definitions
     */
    private static function get_table_transforms()
    {
        return [['blockName' => 'core/table', 'priority' => 10, 'selector' => 'table', 'isMatch' => function ($element) {
            return $element->get_tag_name() === 'TABLE';
        }, 'transform' => function ($element) {
            return self::create_table_block_from_element($element);
        }]];
    }
    /**
     * Creates a table block from an HTML element
     *
     * @param HTML_To_Blocks_HTML_Element $table_element The table element
     * @return array Block array
     */
    private static function create_table_block_from_element($table_element)
    {
        $table_html = $table_element->get_outer_html();
        $processor = \WP_HTML_Processor::create_fragment($table_html);
        if (!$processor) {
            return HTML_To_Blocks_Block_Factory::create_block('core/table', []);
        }
        $current_section = 'body';
        $current_row = [];
        $rows_head = [];
        $rows_body = [];
        $rows_foot = [];
        $caption_text = '';
        $html_offset = 0;
        while ($processor->next_tag(['tag_closers' => 'visit'])) {
            $tag = $processor->get_tag();
            $is_closer = $processor->is_tag_closer();
            if ($tag === 'THEAD' && !$is_closer) {
                $current_section = 'head';
            } elseif ($tag === 'TBODY' && !$is_closer) {
                $current_section = 'body';
            } elseif ($tag === 'TFOOT' && !$is_closer) {
                $current_section = 'foot';
            } elseif ($tag === 'TR' && !$is_closer) {
                $current_row = [];
            } elseif ($tag === 'TR' && $is_closer) {
                if (!empty($current_row)) {
                    $row_data = ['cells' => $current_row];
                    if ($current_section === 'head') {
                        $rows_head[] = $row_data;
                    } elseif ($current_section === 'foot') {
                        $rows_foot[] = $row_data;
                    } else {
                        $rows_body[] = $row_data;
                    }
                }
                $current_row = [];
            } elseif (($tag === 'TD' || $tag === 'TH') && !$is_closer) {
                $cell_data = ['content' => '', 'tag' => \strtolower($tag)];
                if ($processor->get_attribute('colspan')) {
                    $cell_data['colspan'] = (int) $processor->get_attribute('colspan');
                }
                if ($processor->get_attribute('rowspan')) {
                    $cell_data['rowspan'] = (int) $processor->get_attribute('rowspan');
                }
                $inner_html = self::extract_cell_content_at_offset($table_html, $html_offset, $tag);
                $cell_data['content'] = $inner_html;
                $current_row[] = $cell_data;
            } elseif ($tag === 'CAPTION' && !$is_closer) {
                $caption_text = self::extract_cell_content_at_offset($table_html, $html_offset, 'CAPTION');
            }
        }
        $attributes = ['head' => $rows_head, 'body' => $rows_body, 'foot' => $rows_foot];
        if ($table_element->has_attribute('id') && $table_element->get_attribute('id') !== '') {
            $attributes['anchor'] = $table_element->get_attribute('id');
        }
        if (!empty($caption_text)) {
            $attributes['caption'] = $caption_text;
        }
        return HTML_To_Blocks_Block_Factory::create_block('core/table', $attributes);
    }
    /**
     * Extracts cell content from table HTML using regex
     *
     * @param string $html   Full table HTML
     * @param int    $offset Current offset position (passed by reference)
     * @param string $tag    Tag name (TD, TH, CAPTION)
     * @return string Cell inner HTML
     */
    private static function extract_cell_content_at_offset(string $html, int &$offset, string $tag): string
    {
        $search_html = \substr($html, $offset);
        $tag_lower = \strtolower($tag);
        $pattern = '/<' . \preg_quote($tag_lower, '/') . '(?:\s[^>]*)?>(.*)$/is';
        if (!\preg_match($pattern, $search_html, $matches, \PREG_OFFSET_CAPTURE)) {
            return '';
        }
        $content_start = $matches[1][1];
        $content = $matches[1][0];
        $close_tag = '</' . $tag_lower . '>';
        $close_pos = \stripos($content, $close_tag);
        if ($close_pos !== \false) {
            $inner_html = \substr($content, 0, $close_pos);
            $offset += $matches[0][1] + \strlen($matches[0][0]) - \strlen($content) + $close_pos + \strlen($close_tag);
            return \trim($inner_html);
        }
        return '';
    }
    /**
     * core/paragraph transforms - p elements (lowest priority, fallback)
     *
     * @return array Transform definitions
     */
    private static function get_paragraph_transforms()
    {
        return [['blockName' => 'core/paragraph', 'priority' => 20, 'selector' => 'p', 'isMatch' => function ($element) {
            return $element->get_tag_name() === 'P';
        }, 'transform' => function ($element) {
            $content = $element->get_inner_html();
            $attributes = ['content' => $content];
            if ($element->has_attribute('id')) {
                $attributes['anchor'] = $element->get_attribute('id');
            }
            if ($element->has_attribute('style')) {
                $style = $element->get_attribute('style');
                if (\preg_match('/text-align:\s*(left|center|right)/i', $style, $matches)) {
                    $attributes['style'] = ['typography' => ['textAlign' => \strtolower($matches[1])]];
                }
            }
            return HTML_To_Blocks_Block_Factory::create_block('core/paragraph', $attributes);
        }]];
    }
}
\class_alias('BlockFormatBridge\Vendor\HTML_To_Blocks_Transform_Registry', 'HTML_To_Blocks_Transform_Registry', \false);
