SF Meta Archives
===========

SF Meta Archives is a plugin for WordPress. It gives you the hability to have archive pages for your post metas. It should be used as a Must-Use plugin (but it works as a regular plugin too of course). For more details, see [http://www.screenfeed.fr/regpm](http://www.screenfeed.fr/regpm) (french).

You have 2 kinds of archive:

* *"Post-type-like" type:* an archive with an url like http://example.com/my-meta/
* *"Taxonomy-like" type:* an archive with an url like http://example.com/my-meta/my-value/

4 functions to use
---

**register_post_meta( $meta_name, $args )**: Register the post metas.

@param *(string)* `$meta_name` The name of your post meta.

@param *(array|object)* `$args` Arguments:

`post_type` *(string|array)* Post types. Optional.

`query_var` *(string)* Used as query variable name: instead of "$meta_name = ...", "$query_var = ..." will be used. Optional, fallback to $meta_name.

`query_value` *(bool)* Set to true if you want a "Taxonomy-like" architecture.

`meta_query` *(array)* Takes an array of meta query arguments arrays (it takes an array of arrays). See [WP_Query](http://codex.wordpress.org/Class_Reference/WP_Query#Custom_Field_Parameters). Required for "Post-type-like".

`rewrite` *(bool|array)* Array of parameters for rewrite (or true/false): slug, with_front, feeds, pages, ep_mask. See [register_post_type()](http://codex.wordpress.org/Function_Reference/register_post_type#Arguments). Optional.

`label` *(string)* Used to filter the wp_title() function for the archive title. Optional, but should be used.

`description` *(string)* Do I really have to explain what is it for? Optional.


**is_post_meta_archive( $query_var = null, $query_value = null )**: Is a meta archive displaying?

@param *(string)* `$query_var` See register_post_meta(). Optional.

@param *(string)* `$query_value` For "Taxonomy-like", can be used to check against a precise meta value. Optional.

@return *(bool)*


**get_post_meta_archive_link( $query_var = null, $meta_value = null, $paged = 1 )**: Url of a meta archive.

@param *(string)* `$query_var` See register_post_meta().

@param *(string)* `$query_value` The meta value. Required for "Taxonomy-like".

@param *(int)* `$paged` Page 1, page 2... Optional.

@return *(string)* The url of the archive page.


**get_post_meta_archive_description( $query_var = null )**: Description of a meta archive.

@param *(string)* `$query_var` See register_post_meta().

@return *(string)* The description of the archive page.

Examples
---
**An archive page listing all Posts having a thumbnail ("Post-type-like")**

	add_action( 'after_setup_theme', 'register_posts_with_thumb_archive' );
	 
	function register_posts_with_thumb_archive() {
		$args = array(
			'query_var'		=> 'with-thumb',
			'meta_query'	=> array(
				array(
					'key'		=> '_thumbnail_id',
					'compare'	=> 'EXISTS',
				),
			),
			'title'	 		=> __( 'Posts with a thumbnail' ),
			'description'	=> __( 'All my Posts with a thumbnail.' ),
		);
		register_post_meta( '_thumbnail_id', $args );
	}

But, it can be even simpler (if *key* is not provided in *meta_query*, `$meta_name` will be used automatically):

	add_action( 'after_setup_theme', 'register_posts_with_thumb_archive' );
	 
	function register_posts_with_thumb_archive() {
		$args = array(
			'query_var'		=> 'with-thumb',
			'title'	 		=> __( 'Posts with a thumbnail' ),
			'description'	=> __( 'All my Posts with a thumbnail.' ),
		);
		register_post_meta( '_thumbnail_id', $args );
	}

To know if we're displaying a meta archive:

	if ( is_post_meta_archive( 'with-thumb' ) ) {
		// ...
	}

Display a link to the archive:

	<a href="<?php echo get_post_meta_archive_link( 'with-thumb' ); ?>"><?php _e( 'Posts with a thumbnail' ); ?></a>

The URL will be *http://example.com/with-thumb/* or *http://example.com?meta=with-thumb*.

Display the description of an archive:

	echo wpautop( get_post_meta_archive_description( 'with-thumb' ) );

This will print *&lt;p>All my Posts with a thumbnail.&lt;/p>*.

**An archive page listing events by city ("Taxonomy-like")**

In this case, "event" is a Custom Post Type, "_city" is a post meta.

	add_action( 'after_setup_theme', 'register_events_city_archive' );
	 
	function register_events_city_archive() {
		$args = array(
			'post_type' 	=> 'event',
			'query_var' 	=> 'city',
			'query_value'	=> true,
			'meta_query'	=> array(
				array(
					'key'		=> '_city',
					'value'		=> '',
					'compare'	=> '!=',
				),
			),
			'title' 	=> __( 'Events by city' ),
		);
		register_post_meta( '_city', $args );
	}

To know if we're displaying a meta archive (any one):

	if ( is_post_meta_archive( 'city' ) ) {
		// ...
	}

or the one for "paris":

	if ( is_post_meta_archive( 'city', 'paris' ) ) {
		// ...
	}


Display a link to an archive:

	<a href="<?php echo get_post_meta_archive_link( 'city', 'paris' ); ?>">Paris</a>

The URL will be *http://example.com/city/paris/* or *http://example.com?city=paris*.

In this situation, be aware of one thing: you may not want to deal with space caracters or uppercase letters…
But the good news is there's enough filters and actions in the plugin to do what you may want.