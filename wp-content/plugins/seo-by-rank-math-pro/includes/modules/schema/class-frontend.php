<?php
/**
 *  Outputs specific schema code from Schema Template
 *
 * @since      1.0.0
 * @package    RankMath
 * @subpackage RankMathPro
 * @author     MyThemeShop <admin@mythemeshop.com>
 */

namespace RankMathPro\Schema;

use RankMath\Helper;
use RankMath\Traits\Hooker;
use RankMath\Schema\DB;
use MyThemeShop\Helpers\Str;
use MyThemeShop\Helpers\HTML;

defined( 'ABSPATH' ) || exit;

/**
 * Schema Frontend class.
 */
class Frontend {

	use Hooker;

	/**
	 * The Constructor.
	 */
	public function __construct() {
		$this->action( 'rank_math/json_ld', 'add_template_schema', 11, 2 );
		$this->action( 'rank_math/json_ld', 'add_about_mention_attributes', 11 );
		$this->action( 'rank_math/json_ld', 'convert_schema_to_item_list', 99, 2 );
		$this->action( 'rank_math/json_ld', 'validate_schema_data', 999 );
		$this->filter( 'rank_math/snippet/rich_snippet_ItemList_entity', 'filter_item_list_schema' );
		$this->filter( 'rank_math/schema/valid_types', 'valid_types' );
		$this->action( 'rank_math/json_ld', 'add_schema_from_shortcode', 12, 2 );

		$this->conditions = [];

		new Snippet_Pro_Shortcode();

		if ( $this->do_filter( 'link/remove_schema_attribute', false ) ) {
			$this->filter( 'the_content', 'remove_schema_attribute', 11 );
		}

		// Schema Preview.
		$this->filter( 'query_vars', 'add_query_vars' );
		$this->filter( 'init', 'add_endpoint' );
		$this->action( 'template_redirect', 'schema_preview_template' );
	}

	/**
	 * Add the 'photos' query variable so WordPress won't mangle it.
	 *
	 * @param array $vars Array of vars.
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'schema-preview';
		return $vars;
	}

	/**
	 * Add endpoint
	 */
	public function add_endpoint() {
		add_rewrite_endpoint( 'schema-preview', EP_PERMALINK );
	}

	/**
	 * Schema preview template
	 */
	public function schema_preview_template() {
		global $wp_query;

		// if this is not a request for schema preview or a singular object then bail.
		if ( ! isset( $wp_query->query_vars['schema-preview'] ) || ! is_singular() ) {
			return;
		}

		header( 'Content-Type: application/json' );

		do_action( 'rank_math/json_ld/preview' );

		exit;
	}

	/**
	 * Add nofollow and target attributes to link.
	 *
	 * @param  string $content Post content.
	 * @return string
	 */
	public function remove_schema_attribute( $content ) {
		preg_match_all( '/<(a\s[^>]+)>/', $content, $matches );
		if ( empty( $matches ) || empty( $matches[0] ) ) {
			return $content;
		}

		foreach ( $matches[0] as $link ) {
			$attrs = HTML::extract_attributes( $link );

			if ( empty( $attrs['data-schema-attribute'] ) ) {
				continue;
			}

			unset( $attrs['data-schema-attribute'] );
			$content = str_replace( $link, '<a' . HTML::attributes_to_string( $attrs ) . '>', $content );
		}

		return $content;
	}

	/**
	 * Filter functiont to extend valid schema types to use in Rank Math generated schema object.
	 *
	 * @param array $types Valid Schema types.
	 *
	 * @return array
	 */
	public function valid_types( $types ) {
		return array_merge( $types, [ 'movie', 'dataset', 'claimreview' ] );
	}

	/**
	 * Get Default Schema Data.
	 *
	 * @param array  $data   Array of json-ld data.
	 * @param JsonLD $jsonld Instance of jsonld.
	 *
	 * @return array
	 */
	public function convert_schema_to_item_list( $data, $jsonld ) {
		$schemas = array_filter(
			$data,
			function( $schema ) {
				if ( ! in_array( $schema['@type'], [ 'Course', 'Movie', 'Recipe', 'Restaurant' ], true ) ) {
					return false;
				}

				return true;
			}
		);

		if ( 2 > count( $schemas ) ) {
			return $data;
		}

		$data['itemList'] = [
			'@type'           => 'ItemList',
			'itemListElement' => [],
		];

		$count = 1;
		foreach ( $schemas as $id => $schema ) {
			unset( $data[ $id ] );
			$schema['url'] = $jsonld->parts['url'] . '#' . $id;

			$data['itemList']['itemListElement'][] = [
				'@type'    => 'ListItem',
				'position' => $count,
				'item'     => $schema,
			];

			$count++;
		}

		return $data;
	}

	/**
	 * Get Default Schema Data.
	 *
	 * @param array  $data   Array of json-ld data.
	 * @param JsonLD $jsonld Instance of jsonld.
	 *
	 * @return array
	 */
	public function add_template_schema( $data, $jsonld ) {
		$schemas = $this->get_schema_templates( $data, $jsonld );
		if ( empty( $schemas ) ) {
			return $data;
		}

		foreach ( $schemas as $schema ) {
			$data = array_merge( $data, $schema );
		}

		return $data;
	}

	/**
	 * Add About & Mention attributes to Webpage schema.
	 *
	 * @param  array $data Array of json-ld data.
	 * @return array
	 */
	public function add_about_mention_attributes( $data ) {
		if ( ! is_singular() || empty( $data['WebPage'] ) ) {
			return $data;
		}

		global $post;
		if ( ! $post->post_content ) {
			return $data;
		}

		preg_match_all( '|<a[^>]+>([^<]+)</a>|', $post->post_content, $matches );
		if ( empty( $matches ) || empty( $matches[0] ) ) {
			return $data;
		}

		foreach ( $matches[0] as $link ) {
			$attrs = HTML::extract_attributes( $link );
			if ( empty( $attrs['data-schema-attribute'] ) ) {
				continue;
			}

			$attributes = explode( ' ', $attrs['data-schema-attribute'] );
			if ( in_array( 'about', $attributes, true ) ) {
				$data['WebPage']['about'][] = [
					'@type'  => 'Thing',
					'name'   => wp_strip_all_tags( $link ),
					'sameAs' => $attrs['href'],
				];
			}

			if ( in_array( 'mentions', $attributes, true ) ) {
				$data['WebPage']['mentions'][] = [
					'@type'  => 'Thing',
					'name'   => wp_strip_all_tags( $link ),
					'sameAs' => $attrs['href'],
				];
			}
		}

		return $data;
	}

	/**
	 * Filter to change the itemList schema data.
	 *
	 * @param array $schema Snippet Data.
	 * @return array
	 */
	public function filter_item_list_schema( $schema ) {
		if ( ! is_archive() ) {
			return $schema;
		}

		$elements = [];
		$count    = 1;
		while ( have_posts() ) {
			the_post();
			$elements[] = [
				'@type'    => 'ListItem',
				'position' => $count,
				'url'      => get_the_permalink(),
			];

			$count++;
		}

		wp_reset_postdata();

		$schema['itemListElement'] = $elements;

		return $schema;
	}

	/**
	 * Validate Schema Data.
	 *
	 * @param array $schemas Array of json-ld data.
	 *
	 * @return array
	 */
	public function validate_schema_data( $schemas ) {
		if ( empty( $schemas ) ) {
			return $schemas;
		}

		$validate_types = [ 'Dataset', 'LocalBusiness' ];
		foreach ( $schemas as $id => $schema ) {
			$type = isset( $schema['@type'] ) ? $schema['@type'] : '';
			if ( ! Str::starts_with( 'schema-', $id ) || ! in_array( $type, $validate_types, true ) ) {
				continue;
			}

			$hash = [
				'isPartOf'   => true,
				'publisher'  => 'LocalBusiness' === $type,
				'inLanguage' => 'LocalBusiness' === $type,
			];

			foreach ( $hash as $property => $value ) {
				if ( ! $value || ! isset( $schema[ $property ] ) ) {
					continue;
				}

				unset( $schemas[ $id ][ $property ] );
			}

			if ( 'Dataset' === $type && ! empty( $schema['publisher'] ) ) {
				$schemas[ $id ]['creator'] = $schema['publisher'];
				unset( $schemas[ $id ]['publisher'] );
			}
		}

		return $schemas;
	}

	/**
	 * Get Schema data from Schema Templates post type.
	 *
	 * @param array  $data   Array of json-ld data.
	 * @param JsonLD $jsonld Instance of jsonld.
	 *
	 * @return array
	 */
	public function add_schema_from_shortcode( $data, $jsonld ) {
		if ( ! is_singular() || ! $this->do_filter( 'rank_math/schema/add_shortcode_schema', true ) ) {
			return $data;
		}

		global $post;
		$blocks = parse_blocks( $post->post_content );
		if ( ! empty( $blocks ) ) {
			foreach ( $blocks as $block ) {
				if ( 'rank-math/rich-snippet' !== $block['blockName'] ) {
					continue;
				}

				$id      = isset( $block['attrs']['id'] ) ? $block['attrs']['id'] : '';
				$post_id = isset( $block['attrs']['post_id'] ) ? $block['attrs']['post_id'] : '';

				if ( ! $id && ! $post_id ) {
					continue;
				}

				$data = array_merge( $data, $this->get_schema_data_by_id( $id, $post_id, $jsonld, $data ) );
			}
		}

		$regex = '/\[rank_math_rich_snippet (.*)\]/m';
		preg_match_all( $regex, $post->post_content, $matches, PREG_SET_ORDER, 0 );
		if ( ! empty( $matches ) ) {
			foreach ( $matches as $key => $match ) {
				parse_str( str_replace( ' ', '&', $match[1] ), $output );

				$post_id = isset( $output['post_id'] ) ? str_replace( [ '"', "'" ], '', $output['post_id'] ) : '';
				$id      = isset( $output['id'] ) ? str_replace( [ '"', "'" ], '', $output['id'] ) : '';
				$data    = array_merge( $data, $this->get_schema_data_by_id( $id, $post_id, $jsonld, $data ) );
			}
		}

		return $data;
	}

	/**
	 * Get Schema data by ID.
	 *
	 * @param string $id   Schema shortcode ID.
	 * @param int    $post_id Post ID.
	 * @param JsonLD $jsonld Instance of jsonld.
	 * @param array  $data   Array of json-ld data.
	 *
	 * @return array
	 */
	private function get_schema_data_by_id( $id, $post_id, $jsonld, $data ) {
		$schemas         = $id ? DB::get_schema_by_shortcode_id( trim( $id ) ) : DB::get_schemas( trim( $post_id ) );
		$current_post_id = get_the_ID();
		if (
			empty( $schemas ) ||
			(
				isset( $schemas['post_id'] ) && $current_post_id === (int) $schemas['post_id']
			) ||
			$post_id === $current_post_id
		) {
			return [];
		}

		$schemas = isset( $schemas['schema'] ) ? [ $schemas['schema'] ] : $schemas;
		$schemas = $jsonld->replace_variables( $schemas );
		$schemas = $jsonld->filter( $schemas, $jsonld, $data );

		if ( ! empty( $schemas[0]['isPrimary'] ) ) {
			unset( $schemas[0]['isPrimary'] );
		}

		return $schemas;
	}

	/**
	 * Get Schema data from Schema Templates post type.
	 *
	 * @param array  $data   Array of json-ld data.
	 * @param JsonLD $jsonld Instance of jsonld.
	 *
	 * @return array
	 */
	private function get_schema_templates( $data, $jsonld ) {
		$templates = get_posts(
			[
				'post_type'   => 'rank_math_schema',
				'numberposts' => -1,
				'fields'      => 'ids',
			]
		);

		if ( empty( $templates ) ) {
			return;
		}

		$newdata = [];
		foreach ( $templates as $template ) {
			$schema = DB::get_schemas( $template );
			if ( ! $this->can_add( current( $schema ) ) ) {
				continue;
			}

			$schema = $jsonld->replace_variables( $schema );
			$schema = $jsonld->filter( $schema, $jsonld, $data );

			$newdata[] = $schema;
		}

		return $newdata;
	}

	/**
	 * Whether schema can be added to current page
	 *
	 * @param array $schema Schema Data.
	 *
	 * @return boolean
	 */
	private function can_add( $schema ) {
		if ( empty( $schema ) || empty( $schema['metadata']['displayConditions'] ) ) {
			return false;
		}

		foreach ( $schema['metadata']['displayConditions'] as $condition ) {
			$operator = $condition['condition'];
			$category = $condition['category'];
			$type     = $condition['type'];
			$value    = $condition['value'];

			$method = "can_add_{$category}";

			$this->conditions[ $category ] = $this->$method( $operator, $type, $value );
		}

		if ( is_singular() && isset( $this->conditions['singular'] ) ) {
			return $this->conditions['singular'];
		}

		if ( ( is_archive() || is_search() ) && isset( $this->conditions['archive'] ) ) {
			return $this->conditions['archive'];
		}

		return ! empty( $this->conditions['general'] );
	}

	/**
	 * Whether schema can be added to current page
	 *
	 * @param string $operator Comparision Operator.
	 *
	 * @return boolean
	 */
	private function can_add_general( $operator ) {
		return 'include' === $operator;
	}

	/**
	 * Whether schema can be added on archive page
	 *
	 * @param string $operator Comparision Operator.
	 * @param string $type     Post/Taxonoy type.
	 * @param string $value    Post/Term ID.
	 *
	 * @return boolean
	 */
	private function can_add_archive( $operator, $type, $value ) {
		if ( 'search' === $type ) {
			return 'include' === $operator && is_search();
		}

		if ( ! is_archive() ) {
			return false;
		}

		if ( 'all' === $type ) {
			return 'include' === $operator;
		}

		if ( 'author' === $type ) {
			return is_author() && 'include' === $operator && is_author( $value );
		}

		if ( 'category' === $type ) {
			return ! is_category() ? $this->conditions['archive'] : 'include' === $operator && is_category( $value );
		}

		if ( 'post_tag' === $type ) {
			return ! is_tag() ? $this->conditions['archive'] : 'include' === $operator && is_tag( $value );
		}

		return 'include' === $operator && is_tax( $type, $value );
	}

	/**
	 * Whether schema can be added on single page
	 *
	 * @param string $operator Comparision Operator.
	 * @param string $type     Post/Taxonoy type.
	 * @param string $value    Post/Term ID.
	 *
	 * @return boolean
	 */
	private function can_add_singular( $operator, $type, $value ) {
		if ( ! is_singular() ) {
			return false;
		}

		if ( 'all' === $type ) {
			return 'include' === $operator;
		}

		if ( ! is_singular( $type ) ) {
			return false;
		}

		if ( ! $value ) {
			return 'include' === $operator;
		}

		if ( ! is_single( $value ) && ! is_page( $value ) ) {
			return $this->conditions['singular'];
		}

		return 'include' === $operator;
	}
}
