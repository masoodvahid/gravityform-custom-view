<?php
/**
 * Remove unanswered or conditionally hidden Gravity Forms fields from annotated
 * detail/print templates before their merge tags are rendered.
 *
 * Supported annotations:
 *   data-gf-field="83"  on the smallest block representing a field.
 *   class="mstid-data-row" on answer rows.
 *   class="mstid-bound" on field blocks (used to detect empty rows).
 *   class="mstid-can-hide" on section tables.
 *   class="mstid-optional-intro" on the introductory note of optional sections.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GFCV_Conditional_Details {
    public static function has_data( $value ) {
        if ( is_array( $value ) || is_object( $value ) ) {
            foreach ( (array) $value as $item ) {
                if ( self::has_data( $item ) ) {
                    return true;
                }
            }
            return false;
        }

        if ( null === $value || false === $value ) {
            return false;
        }

        $text = trim( (string) $value );
        return '' !== $text && '[]' !== $text && '{}' !== $text && 'a:0:{}' !== $text;
    }

    public static function entry_has_value( $entry, $field ) {
        $id = (string) $field->id;

        if ( class_exists( 'GFFormsModel' ) && method_exists( 'GFFormsModel', 'get_lead_field_value' ) ) {
            if ( self::has_data( GFFormsModel::get_lead_field_value( $entry, $field ) ) ) {
                return true;
            }
        }

        if ( isset( $entry[ $id ] ) && self::has_data( $entry[ $id ] ) ) {
            return true;
        }

        foreach ( $entry as $key => $value ) {
            if ( 0 === strpos( (string) $key, $id . '.' ) && self::has_data( $value ) ) {
                return true;
            }
        }

        return false;
    }

    private static function has_class( $node, $class ) {
        return false !== strpos( ' ' . $node->getAttribute( 'class' ) . ' ', ' ' . $class . ' ' );
    }

    private static function remove( $node ) {
        if ( $node->parentNode ) {
            $node->parentNode->removeChild( $node );
        }
    }

    public static function prepare( $template, $form, $entry, $respect_conditional_logic = true ) {
        // Legacy templates are unaffected; the feature is opt-in per field.
        if ( false === strpos( $template, 'data-gf-field=' ) ) {
            return $template;
        }

        if ( ! class_exists( 'DOMDocument' ) || ! class_exists( 'DOMXPath' )
            || ! class_exists( 'GFFormsModel' ) || ! method_exists( 'GFFormsModel', 'is_field_hidden' )
            || empty( $form['fields'] ) || ! is_array( $entry ) ) {
            return $template;
        }

        $fields = array();
        foreach ( $form['fields'] as $field ) {
            if ( isset( $field->id ) ) {
                $fields[ (string) $field->id ] = $field;
            }
        }

        $dom = new DOMDocument( '1.0', 'UTF-8' );
        $old_errors = libxml_use_internal_errors( true );
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'
            . '<div id="gfcv-details-root">' . $template . '</div></body></html>';
        $loaded = $dom->loadHTML( $html );
        libxml_clear_errors();
        libxml_use_internal_errors( $old_errors );

        if ( ! $loaded ) {
            return $template;
        }

        $xpath = new DOMXPath( $dom );
        $roots = $xpath->query( '//*[@id="gfcv-details-root"]' );
        if ( ! $roots || ! $roots->length ) {
            return $template;
        }
        $root = $roots->item( 0 );

        // Evaluate actual Gravity Forms conditional logic against this saved entry.
        $nodes = iterator_to_array( $xpath->query( './/*[@data-gf-field]', $root ) );
        foreach ( $nodes as $node ) {
            $field_id = $node->getAttribute( 'data-gf-field' );
            if ( ! isset( $fields[ $field_id ] ) ) {
                continue; // Do not discard an unknown field.
            }

            $field = $fields[ $field_id ];
            $hidden = $respect_conditional_logic && (bool) GFFormsModel::is_field_hidden( $form, $field, array(), $entry );
            if ( $respect_conditional_logic && ( $hidden || ! self::entry_has_value( $entry, $field ) ) ) {
                self::remove( $node );
            }
        }

        // A two-column row expands to one column if only one answer exists.
        $rows = iterator_to_array( $xpath->query(
            './/tr[contains(concat(" ",normalize-space(@class)," ")," mstid-data-row ")]', $root
        ) );
        foreach ( $rows as $row ) {
            $blocks = $xpath->query(
                './/*[contains(concat(" ",normalize-space(@class)," ")," mstid-bound ")]', $row
            );
            if ( ! $blocks || ! $blocks->length ) {
                self::remove( $row );
                continue;
            }

            $cells = iterator_to_array( $xpath->query( './td', $row ) );
            foreach ( $cells as $cell ) {
                $inside = $xpath->query(
                    './/*[contains(concat(" ",normalize-space(@class)," ")," mstid-bound ")]', $cell
                );
                if ( ! self::has_class( $cell, 'mstid-bound' ) && ( ! $inside || ! $inside->length ) ) {
                    self::remove( $cell );
                }
            }

            // The template can declare either two or three columns. Fill the
            // complete row width after hidden conditional fields are pruned.
            $table = $row->parentNode;
            while ( $table && 'table' !== $table->nodeName ) {
                $table = $table->parentNode;
            }
            $columns = ( $table && $table->hasAttribute( 'data-mstid-columns' ) )
                ? max( 1, min( 6, absint( $table->getAttribute( 'data-mstid-columns' ) ) ) )
                : 2;
            $remaining = iterator_to_array( $xpath->query( './td', $row ) );
            $count = count( $remaining );
            if ( $count > 0 ) {
                $span_sum = 0;
                foreach ( $remaining as $cell ) {
                    $span_sum += max( 1, (int) $cell->getAttribute( 'colspan' ) );
                }
                if ( $span_sum < $columns ) {
                    $last = $remaining[ $count - 1 ];
                    $last_span = max( 1, (int) $last->getAttribute( 'colspan' ) );
                    $last->setAttribute( 'colspan', (string) ( $last_span + $columns - $span_sum ) );
                }
                if ( 1 === $count ) {
                    $cell = $remaining[0];
                    $cell->setAttribute( 'colspan', (string) $columns );
                    // Drop a legacy fixed 50%/33.333% cell width.
                    $style = preg_replace( '/width\\s*:\\s*(?:50|33(?:\\.333)?)%\\s*;?/i', '', $cell->getAttribute( 'style' ) );
                    if ( $style !== $cell->getAttribute( 'style' ) ) {
                        $cell->setAttribute( 'style', $style );
                    }
                }
            }
        }

        // Remove headings together with entirely empty sections.
        $sections = iterator_to_array( $xpath->query(
            './/table[contains(concat(" ",normalize-space(@class)," ")," mstid-can-hide ")]', $root
        ) );
        foreach ( $sections as $section ) {
            $remaining = $xpath->query( './/*[@data-gf-field]', $section );
            if ( ! $remaining || ! $remaining->length ) {
                self::remove( $section );
            }
        }

        $optional = $xpath->query( './/table[@data-mstid-optional="yes"]', $root );
        if ( ! $optional || ! $optional->length ) {
            $intros = iterator_to_array( $xpath->query(
                './/*[contains(concat(" ",normalize-space(@class)," ")," mstid-optional-intro ")]', $root
            ) );
            foreach ( $intros as $intro ) {
                self::remove( $intro );
            }
        }

        $output = '';
        foreach ( $root->childNodes as $child ) {
            $output .= $dom->saveHTML( $child );
        }

        return $output;
    }
}
