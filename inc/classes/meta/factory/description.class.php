<?php
/**
 * @package The_SEO_Framework\Classes\Front\Meta\Factory
 * @subpackage The_SEO_Framework\Meta\Description
 */

namespace The_SEO_Framework\Meta\Factory;

\defined( 'THE_SEO_FRAMEWORK_PRESENT' ) or die;

use \The_SEO_Framework\Helper\Query,
	\The_SEO_Framework\Meta\Factory;

use function \The_SEO_Framework\{
	memo,
	Utils\normalize_generation_args,
	Utils\clamp_sentence
};

/**
 * The SEO Framework plugin
 * Copyright (C) 2023 Sybre Waaijer, CyberWire B.V. (https://cyberwire.nl/)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 3 as published
 * by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Holds getters for meta tag output.
 *
 * @since 4.3.0
 * @access protected
 * @internal Use tsf()->description() instead.
 */
class Description {

	/**
	 * Returns the meta description from custom fields. Falls back to autogenerated description.
	 *
	 * @since 4.3.0
	 *
	 * @param array|null $args   The query arguments. Accepts 'id', 'taxonomy', and 'pta'.
	 *                           Leave null to autodetermine query.
	 * @param bool       $escape Whether to escape the description.
	 * @return string The real description output.
	 */
	public static function get_description( $args = null, $escape = true ) {

		$desc = static::get_custom_description( $args, false )
			 ?: static::get_generated_description( $args, false );

		return $escape ? \tsf()->escape_description( $desc ) : $desc;
	}

	/**
	 * Returns the custom user-inputted description.
	 *
	 * @since 4.3.0
	 *
	 * @param array|null $args   The query arguments. Accepts 'id', 'taxonomy', and 'pta'.
	 *                           Leave null to autodetermine query.
	 * @param bool       $escape Whether to escape the description.
	 * @return string The custom field description.
	 */
	public static function get_custom_description( $args = null, $escape = true ) {

		if ( null === $args ) {
			$desc = static::get_custom_description_from_query();
		} else {
			normalize_generation_args( $args );
			$desc = static::get_custom_description_from_args( $args );
		}

		/**
		 * @since 2.9.0
		 * @since 3.0.6 1. Duplicated from $this->generate_description() (deprecated)
		 *              2. Removed all arguments but the 'id' argument.
		 * @since 4.2.0 1. No longer gets supplied custom query arguments when in the loop.
		 *              2. Now supports the `$args['pta']` index.
		 * @param string     $desc The custom-field description.
		 * @param array|null $args The query arguments. Contains 'id', 'taxonomy', and 'pta'.
		 *                         Is null when the query is auto-determined.
		 */
		$desc = (string) \apply_filters_ref_array(
			'the_seo_framework_custom_field_description',
			[
				$desc,
				$args,
			]
		);

		return $escape ? \tsf()->escape_description( $desc ) : $desc;
	}

	/**
	 * Returns the autogenerated meta description.
	 *
	 * @since 3.0.6
	 * @since 3.1.0 1. The first argument now accepts an array, with "id" and "taxonomy" fields.
	 *              2. No longer caches.
	 *              3. Now listens to option.
	 *              4. Added type argument.
	 * @since 3.1.2 1. Now omits additions when the description will be deemed too short.
	 *              2. Now no longer converts additions into excerpt when no excerpt is found.
	 * @since 3.2.2 Now converts HTML characters prior trimming.
	 * @since 4.2.0 Now supports the `$args['pta']` index.
	 * @since 4.3.0 Moved to \The_SEO_Framework\Meta\Factory\Description.
	 *
	 * @TODO Should we enforce a minimum description length, where this result is ignored? e.g., use the input
	 *       guidelines' 'lower' value as a minimum, so that TSF won't ever generate "bad" descriptions?
	 *       This isn't truly helpful, since then search engines can truly fetch whatever with zero guidance.
	 *
	 * @param array|null $args   The query arguments. Accepts 'id', 'taxonomy', and 'pta'.
	 *                           Leave null to autodetermine query.
	 * @param bool       $escape Whether to escape the description.
	 * @param string     $type   Type of description. Accepts 'search', 'opengraph', 'twitter'.
	 * @return string The generated description output.
	 */
	public static function get_generated_description( $args = null, $escape = true, $type = 'search' ) {

		if ( ! static::may_generate( $args ) ) return '';

		switch ( $type ) {
			case 'opengraph':
			case 'twitter':
			case 'search':
				break;
			default:
				$type = 'search';
		}

		isset( $args ) and normalize_generation_args( $args );

		/**
		 * @since 2.9.0
		 * @since 3.1.0 No longer passes 3rd and 4th parameter.
		 * @since 4.0.0 1. Deprecated second parameter.
		 *              2. Added third parameter: $args.
		 * @since 4.2.0 Now supports the `$args['pta']` index.
		 * @param string     $excerpt The excerpt to use.
		 * @param int        $page_id Deprecated.
		 * @param array|null $args The query arguments. Contains 'id', 'taxonomy', and 'pta'.
		 *                         Is null when the query is auto-determined.
		 * @todo deprecate and shift input for new filter.
		 */
		$excerpt = (string) \apply_filters_ref_array(
			'the_seo_framework_fetched_description_excerpt',
			[
				Description\Excerpt::get_excerpt( $args ),
				0,
				$args,
			]
		);

		// This page has a generated description that's far too short: https://theseoframework.com/em-changelog/1-0-0-amplified-seo/.
		// A direct directory-'site:' query will accept the description outputted--anything else will ignore it...
		// We should not work around that, because it won't direct in the slightest what to display.
		$excerpt = clamp_sentence(
			$excerpt,
			0,
			\tsf()->get_input_guidelines()['description'][ $type ]['chars']['goodUpper']
		);

		/**
		 * @since 2.9.0
		 * @since 3.1.0 No longer passes 3rd and 4th parameter.
		 * @since 4.2.0 Now supports the `$args['pta']` index.
		 * @param string     $desc The generated description.
		 * @param array|null $args The query arguments. Contains 'id', 'taxonomy', and 'pta'.
		 *                         Is null when the query is auto-determined.
		 */
		$desc = (string) \apply_filters_ref_array(
			'the_seo_framework_generated_description',
			[
				$excerpt,
				$args,
			]
		);

		return $escape ? \tsf()->escape_description( $desc ) : $desc;
	}

	/**
	 * Gets a custom description, based on expected or current query, without escaping.
	 *
	 * @since 4.3.0
	 * @see static::get_custom_description()
	 *
	 * @return string The custom description.
	 */
	public static function get_custom_description_from_query() {

		if ( Query::is_real_front_page() ) {
			if ( Query::is_static_frontpage() ) {
				$desc = \tsf()->get_option( 'homepage_description' )
					 ?: \tsf()->get_post_meta_item( '_genesis_description' );
			} else {
				$desc = \tsf()->get_option( 'homepage_description' );
			}
		} elseif ( Query::is_singular() ) {
			$desc = \tsf()->get_post_meta_item( '_genesis_description' );
		} elseif ( Query::is_editable_term() ) {
			$desc = \tsf()->get_term_meta_item( 'description' );
		} elseif ( \is_post_type_archive() ) {
			/**
			 * @since 4.0.6
			 * @since 4.2.0 Deprecated.
			 * @deprecated Use options instead.
			 * @param string $desc The post type archive description.
			 */
			$desc = (string) \apply_filters_deprecated(
				'the_seo_framework_pta_description',
				[ \tsf()->get_post_type_archive_meta_item( 'description' ) ?: '' ],
				'4.2.0 of The SEO Framework'
			);
		}

		return $desc ?? '' ?: '';
	}

	/**
	 * Gets a custom description, based on input arguments query, without escaping.
	 *
	 * @since 3.1.0
	 * @since 3.2.2 Now tests for the static frontpage metadata prior getting fallback data.
	 * @since 4.2.0 Now supports the `$args['pta']` index.
	 * @since 4.3.0 1. Now expects an ID before getting a post meta item.
	 *              2. Moved to \The_SEO_Framework\Meta\Factory\Description.
	 *
	 * @param array|null $args The query arguments. Accepts 'id', 'taxonomy', and 'pta'.
	 *                         Leave null to autodetermine query.
	 * @return string The custom description.
	 */
	public static function get_custom_description_from_args( $args ) {

		if ( $args['taxonomy'] ) {
			$desc = \tsf()->get_term_meta_item( 'description', $args['id'] );
		} elseif ( $args['pta'] ) {
			$desc = \tsf()->get_post_type_archive_meta_item( 'description', $args['pta'] );
		} elseif ( Query::is_real_front_page_by_id( $args['id'] ) ) {
			if ( $args['id'] ) {
				$desc = \tsf()->get_option( 'homepage_description' )
					 ?: \tsf()->get_post_meta_item( '_genesis_description', $args['id'] );
			} else {
				$desc = \tsf()->get_option( 'homepage_description' );
			}
		} elseif ( $args['id'] ) {
			$desc = \tsf()->get_post_meta_item( '_genesis_description', $args['id'] );
		}

		return $desc ?? '' ?: '';
	}

	/**
	 * Determines whether automated descriptions are enabled.
	 *
	 * @since 4.3.0
	 *
	 * @param array|null $args The query arguments. Accepts 'id', 'taxonomy', and 'pta'.
	 *                         Leave null to autodetermine query.
	 * @return bool
	 */
	public static function may_generate( $args = null ) {

		isset( $args ) and normalize_generation_args( $args );

		/**
		 * @since 2.5.0
		 * @since 3.0.0 Now passes $args as the second parameter.
		 * @since 3.1.0 Now listens to option.
		 * @since 4.2.0 Now supports the `$args['pta']` index.
		 * @param bool       $autodescription Enable or disable the automated descriptions.
		 * @param array|null $args            The query arguments. Contains 'id', 'taxonomy', and 'pta'.
		 *                                    Is null when the query is auto-determined.
		 */
		return (bool) \apply_filters_ref_array(
			'the_seo_framework_enable_auto_description',
			[
				\tsf()->get_option( 'auto_description' ),
				$args,
			]
		);
	}
}
