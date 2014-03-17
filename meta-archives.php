<?php
/*
 * Plugin Name: SF Meta Archives
 * Plugin URI: http://www.screenfeed.fr
 * Description: Gives you the hability to have archive pages for your post metas.
 * Version: 1.0
 * Author: Grégory Viguier
 * Author URI: http://www.screenfeed.fr/greg/
 * License: GPLv3
 * License URI: http://www.screenfeed.fr/gpl-v3.txt
 */

if( !defined( 'ABSPATH' ) )
	die( 'Cheatin\' uh?' );

/*-------------------------------------------------------------------------------*/
/* !TEMPLATE TAGS ============================================================== */
/*-------------------------------------------------------------------------------*/

/*
 * Register the post metas
 *
 * Definitions:
 * "Post-type-like" type: an archive with an url like http://example.com/my-meta/
 * "Taxonomy-like" type: an archive with an url like http://example.com/my-meta/my-value/
 *
 * @param (string)       $meta_name The name of your post meta.
 * @param (array|object) $args Parameters:
 *      post_type   (string|array) Post types. Optional.
 *      query_var   (string)       Used as query variable name: instead of "$meta_name = ...", "$query_var = ..." will be used. Optional, fallback to $meta_name.
 *      query_value (bool)         Set to true if you want a "Taxonomy-like" architecture.
 *      meta_query  (array)        Takes an array of meta query arguments arrays (it takes an array of arrays). See http://codex.wordpress.org/Class_Reference/WP_Query#Custom_Field_Parameters. Required for "Post-type-like".
 *      rewrite     (bool|array)   Array of parameters for rewrite (or true/false): slug, with_front, feeds, pages, ep_mask. Optional.
 *      label       (string)       Used to filter the wp_title() function for the archive title. Optional, but should be used.
 *      description (string)       If someday you need one... (will be stored in the global var). Optional.
 */

function register_post_meta( $meta_name, $args ) {
	global $wp_metas, $wp_rewrite, $wp_query, $wp;

	if ( !$meta_name || empty($args) )
		return;		// PEBKAC

	if ( !did_action('setup_theme') || did_action('query_vars') )
		_doing_it_wrong( __FUNCTION__, sprintf( __( '%1$s should be used after the %2$s hook, and before the %3$s hook.' ), '<code>sf_register_meta</code>', '<code>setup_theme</code>', '<code>query_vars</code>' ) );

	$args = (object) array_merge( array(
		'post_type'		=> null,
		'query_var'		=> null,
		'query_value'	=> false,
		'meta_query'	=> array(),
		'rewrite'		=> true,
		'label'			=> __('Meta Archive'),
		'description'	=> '',
	), (array) $args );

	$args->query_var	= $args->query_var ? $args->query_var : $meta_name;

	if ( !empty($wp) ) {
		if ( $args->query_value )
			$wp->add_query_var( $args->query_var );
		else
			$wp->add_query_var( 'meta' );
	}

	if ( false !== $args->rewrite && ( is_admin() || '' != get_option( 'permalink_structure' ) ) ) {

		$args->rewrite	= is_array($args->rewrite) ? $args->rewrite : array();
		$args->rewrite	= array_merge( array(
			'slug'			=> $args->query_var,
			'with_front'	=> true,
			'feeds'			=> false,
			'pages'			=> true,
			'ep_mask'		=> EP_NONE,
		), $args->rewrite );
		$args->rewrite['hierarchical'] = false;

		$archive_slug	= $args->rewrite['slug'];

		if ( $args->query_value ) {
			// Taxonomy-like
			add_rewrite_tag( '%'.$args->query_var.'%', '([^/]+)', $args->query_var.'=' );
			add_permastruct( $args->query_var, $archive_slug.'/%'.$args->query_var.'%', $args->rewrite );
		}
		else {
			// Post-type-like
			$base_url = 'index.php?meta=' . $args->query_var;

			if ( $args->rewrite['with_front'] )
				$archive_slug = substr( $wp_rewrite->front, 1 ) . $archive_slug;
			else
				$archive_slug = $wp_rewrite->root . $archive_slug;

			add_rewrite_rule( $archive_slug . '/?$', $base_url, 'top' );

			if ( $args->rewrite['feeds'] && $wp_rewrite->feeds ) {
				$feeds = '(' . trim( implode( '|', $wp_rewrite->feeds ) ) . ')';
				add_rewrite_rule( $archive_slug . '/feed/' . $feeds . '/?$', $base_url . '&feed=$matches[1]', 'top' );
				add_rewrite_rule( $archive_slug . '/' . $feeds . '/?$', $base_url . '&feed=$matches[1]', 'top' );
			}

			if ( $args->rewrite['pages'] )
				add_rewrite_rule( $archive_slug . '/' . $wp_rewrite->pagination_base . '/([0-9]{1,})/?$', $base_url .'&paged=$matches[1]', 'top' );
		}

	}

	$args->meta					= $meta_name;
	$args->post_types			= $args->post_type;
	unset($args->post_type);
	$wp_metas[$args->query_var]	= $args;

	do_action( 'registered_post_meta', $meta_name, $args );
}


/*
 * Is a meta archive displaying?
 *
 * @param $query_var   (string) See register_post_meta(). Optional.
 * @param $query_value (string) For "Taxonomy-like", can be used to check against a precise meta value. Optional.
 * @return (bool).
 */

function is_post_meta_archive( $query_var = null, $query_value = null ) {
	global $wp_query;
	if ( empty($wp_query->is_post_meta_archive) && empty($wp_query->is_post_meta) )
		return null;

	if ( is_null($query_var) )
		return $wp_query->is_post_meta_archive || $wp_query->is_post_meta;

	if ( $wp_query->is_post_meta_archive )
		return $wp_query->is_post_meta_archive && $wp_query->get('meta') == $query_var;

	if ( is_null($query_value) )
		return $wp_query->is_post_meta && $wp_query->queried_object->query_var == $query_var;

	return $wp_query->is_post_meta && $wp_query->get( $wp_query->queried_object->query_var ) == $query_value;
}


/*
 * Url of a meta archive
 *
 * @param $query_var  (string) See register_post_meta(). Required.
 * @param $meta_value (string) The meta value. Required for "Taxonomy-like".
 * @param $paged      (int)    Page 1, page 2... Optional.
 * @return (string) The url of the archive page.
 */

function get_post_meta_archive_link( $query_var, $meta_value = null, $paged = 1 ) {
	global $wp_metas, $wp_rewrite, $wp_query;
	if ( empty($wp_metas[$query_var]) )
		return false;

	$meta			= $wp_metas[$query_var];
	$paged			= absint( $paged );

	if ( $wp_query->is_post_meta && !$meta_value )
		return null;

	if ( $paged > 1 && is_post_meta_archive( $meta->query_var ) ) {
		$max_paged	= $wp_query->post_count ? ceil( $wp_query->found_posts / $wp_query->post_count ) : 1;
		$paged		= min( $paged, $max_paged );
	}

	if ( false !== $meta->rewrite && '' != get_option( 'permalink_structure' ) ) {

		$archive_slug		= !empty($meta->rewrite['slug']) ? $meta->rewrite['slug'] : $meta->query_var;

		if ( !empty($meta->rewrite['with_front']) )
			$archive_slug	= substr( $wp_rewrite->front, 1 ) . $archive_slug;
		else
			$archive_slug	= $wp_rewrite->root . $archive_slug;

		if ( $wp_query->is_post_meta )
			$archive_slug  .= '/' . $meta_value;

		if ( $paged > 1 && !empty($meta->rewrite['pages']) ) {
			$pagination_base = $wp_rewrite->pagination_base ? $wp_rewrite->pagination_base . '/' : '';
			$archive_slug  .= '/' . $pagination_base . $paged;
		}

		$url = $archive_slug . '/';

	}
	else {

		if ( $wp_query->is_post_meta )
			$base_url = '?' . $meta->query_var . '=' . $meta_value . ( $paged > 1 && !empty($meta->rewrite['pages']) ? '&paged=' . $paged : '' );
		else
			$base_url = '?meta=' . $meta->query_var . ( $paged > 1 && !empty($meta->rewrite['pages']) ? '&paged=' . $paged : '' );

		$url = $base_url;

	}

	$url = apply_filters( 'post-metas_archive_link', home_url( $url ), $query_var, $meta_value, $paged );
	return esc_url( $url );
}

/*-------------------------------------------------------------------------------*/
/* !FILTERS ==================================================================== */
/*-------------------------------------------------------------------------------*/

/*
 * Set all the is_post_meta, queried_object, etc properties in the queries.
 */

add_action( 'parse_query', 'sf_meta_parse_query', 1 );

function sf_meta_parse_query( $query ) {
	global $wp_metas;
	$query->is_post_meta = false;
	$query->is_post_meta_archive = false;

	if ( empty($wp_metas) )
		return;

	if ( ($query_var = $query->get('meta')) && !empty($wp_metas[$query_var]) ) {
		$query->is_post_meta_archive	= true;
		$query->is_home					= false;
		$query->is_archive				= true;
		$query->queried_object			= $wp_metas[$query_var];
		$query->queried_object_id		= $query_var;
		return;
	}
	foreach ( $wp_metas as $query_var => $args ) {
		if ( $query->get( $args->query_var ) ) {
			$query->is_post_meta		= true;
			$query->is_home				= false;
			$query->is_archive			= true;
			$query->queried_object		= $args;
			$query->queried_object_id	= $query_var;
			return;
		}
	}

}


/*
 * Set the meta query, post type and posts per page.
 */

add_action( 'pre_get_posts', 'sf_meta_pre_get_posts', 1 );

function sf_meta_pre_get_posts( $query ) {
	if ( $query->is_post_meta_archive || $query->is_post_meta ) {

		if ( $post_types = $query->queried_object->post_types ) {
			$post_types = is_array($post_types) ? $post_types : explode(',', $post_types);
			$query->set( 'post_type', $post_types );
		}


		if ( $query->is_post_meta_archive ) {
			if ( !empty($query->queried_object->posts_per_page) ) {
				$query->set( 'posts_per_page', (int) $query->queried_object->posts_per_page );
			}
			if ( !$query->get( 'posts_per_page') ) {
				$query->set( 'posts_per_page', (int) apply_filters( 'post-metas_per_page', get_option('posts_per_page', 10) ) );
			}
		}

		$obj				= $query->queried_object;
		$obj->meta_query	= is_array($obj->meta_query) ? $obj->meta_query : array();
		$meta_query			= array();

		if ( $query->is_post_meta ) {
			$meta_query['value'] = $query->get( $obj->query_var );
			$meta_query['value'] = apply_filters( 'post-metas_query_value', $meta_query['value'], $query, 'query' );
		}

		$obj->meta_query[0]	= !empty($obj->meta_query[0]) ? array_merge($meta_query, $obj->meta_query[0]) : $meta_query;

		foreach ( $obj->meta_query as $i => $mq ) {
			if ( empty($mq['key']) ) {
				$obj->meta_query[$i]['key'] = $query->queried_object->meta;
			}
		}

		$old_meta_query		= $query->get( 'meta_query' );
		$old_meta_query		= is_array( $old_meta_query ) ? $old_meta_query : array();
		$query->set( 'meta_query', array_merge( $old_meta_query, $obj->meta_query ) );
	}
}


/*
 * Set the templates.
 */

add_filter( 'template_include', 'sf_meta_archive_template', 1 );

function sf_meta_archive_template( $template ) {
	if ( is_post_meta_archive() ) {
		global $wp_query;
		$args			= get_queried_object();
		$templates		= array();

		if ( $wp_query->is_post_meta ) {
			$meta_value  = get_query_var( $args->query_var );
			$meta_value  = apply_filters( 'post-metas_query_value', $meta_value, $wp_query, 'template' );
			$meta_value  = sanitize_file_name( $meta_value );
			$templates[] = 'post-meta-' . $args->query_var . '-' . $meta_value . '.php';
			$templates[] = 'post-meta-' . $args->meta . '-' . $meta_value . '.php';
		}

		$templates[] = 'post-meta-' . $args->query_var . '.php';
		$templates[] = 'post-meta-' . $args->meta . '.php';
		$templates[] = 'post-meta.php';

		$new_template = get_query_template( 'post-meta', $templates );
		return $new_template ? $new_template : $template;
	}
	return $template;
}


/*
 * Set the title.
 */

add_filter( 'wp_title', 'sf_meta_archive_title', 1 );

function sf_meta_archive_title( $title ) {
	if ( is_post_meta_archive() ) {
		$queried_object = get_queried_object();
		$title = $queried_object->label ? $queried_object->label : __('Archives');
		return apply_filters( 'post-metas_archive_title', $title );
	}
	return $title;
}


/**/