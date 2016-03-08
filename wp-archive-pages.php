<?php
/*
Plugin Name: Archive Pages
Plugin URI:  https://github.com/creativecoder/wp-archive-pages
Description: Creates a page to hold settings for each post type archive page on your site
Version:     0.1.1
Author:      Grant Kinney
Author URI:  https://github.com/creativecoder
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: archive-pages
*/

class Archive_Pages {

	/**
	 * Name of archive page post type
	 */
	const POST_TYPE = 'archive_page';

	/**
	 * Name of meta key used for post meta to connect archive pages with registered custom post types
	 */
	const META_KEY = '_post_type_archive';

	/**
	 * Capability required for editing archive pages
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Post types to exclude from having an archive page
	 * @var array
	 */
	public $excluded_post_types = array();

	public function __construct() {
		$this->excluded_post_types = array( self::POST_TYPE, 'page', 'attachment' );

		add_action( 'init', array($this, 'register_post_type') );
		add_action( 'admin_init', array($this, 'add_archive_pages') );
		add_action( 'admin_menu', array($this, 'add_submenus') );
		add_filter( 'post_type_link', array($this, 'archive_page_link'), 10, 4 );
	}

	/**
	 * Register archive page post type
	 * @return void
	 */
	public function register_post_type() {
		$args = array(
			'public'             => true,
			'show_ui'            => false,
			'show_in_menu'       => false,
			// Don't map meta capabilities since we're explicitly defining them below
			'map_meta_cap'       => false,
			// Set to an empty string since we are manually specifying the capabilities, below
			'capability_type'    => '',
			// We set these manually to allow editing and disallow creating and deleting
			'capabilities' => array(
				'edit_post'              => self::CAPABILITY,
				'read_post'              => self::CAPABILITY,
				'delete_post'            => false,
				'edit_posts'             => self::CAPABILITY,
				'edit_others_posts'      => self::CAPABILITY,
				'publish_posts'          => self::CAPABILITY,
				'read_private_posts'     => self::CAPABILITY,
				'delete_posts'           => false,
				'delete_private_posts'   => false,
				'delete_published_posts' => false,
				'delete_others_posts'    => false,
				'edit_private_posts'     => self::CAPABILITY,
				'edit_published_posts'   => self::CAPABILITY,
				'create_posts'           => false,
			),
			'hierarchical'       => false,
			'supports'           => array(
				'title',
				'editor',
				'author',
				'thumbnail',
				'excerpt',
				'comments',
			),
			'has_archive'        => false,
			'rewrite'            => false,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Add archive pages for custom post types
	 */
	public function add_archive_pages() {
		$post_types = $this->_get_post_types();

		foreach( $post_types as $post_type ) {

			$post = get_post_type_archive_page($post_type);

			if ( false === $post ) {
				$post_data = array(
					'post_type'   => self::POST_TYPE,
					'post_title'  => $this->_get_post_type_label( $post_type ),
					'post_status' => 'publish',
					'post_name'   => $post_type
				);

				$post_id = wp_insert_post($post_data);
				$post = get_post($post_id);

				update_post_meta( $post_id, self::META_KEY, $post_type );
			}
		}
	}

	/**
	 * Add wp-admin menu items to edit archive pages for each post type
	 */
	public function add_submenus() {

		// Exit early if current user doesn't have required capabilities
		if ( ! current_user_can(self::CAPABILITY) ) return;

		global $submenu;

		$post_types = $this->_get_post_types();

		foreach( $post_types as $post_type ) {

			$parent_slug = $this->_get_parent_slug( $post_type );

			$label = $this->_get_post_type_label( $post_type ) . ' Archive Page';

			$path = get_edit_archive_page_link( $post_type );

			$submenu[$parent_slug][] = array( $label, self::CAPABILITY, $path );
		}
	}

	/**
	 * Filter archive page link to call the actual WordPress archive page for each post type
	 *
	 * @param string  $post_link The post's permalink.
	 * @param WP_Post $post      The post in question.
	 * @param bool    $leavename Whether to keep the post name.
	 * @param bool    $sample    Is it a sample permalink.
	 * @return string            Filtered post permalink
	 */
	public function archive_page_link( $post_link, $post, $leavename, $sample ) {
		if ( $post->post_type === self::POST_TYPE ) {

			$post_type = get_post_meta( $post->ID, self::META_KEY, true );

			if ( 'post' === $post_type ) {
				$post_link = get_permalink( get_option( 'page_for_posts' ) );
			} else {
				$post_link = get_post_type_archive_link( $post_type );
			}
		}

		return $post_link;
	}

	/**
	 * Get post types to connect to an archive page, filtered excluded post types
	 *
	 * @return array       Custom post types
	 */
	protected function _get_post_types() {
		$public_post_types = get_post_types( array('public' => true) );
		$post_types = array_filter( $public_post_types, array($this, '_filter_post_types') );

		return $post_types;
	}

	/**
	 * Callback to filter array of post types, excluding specified post types
	 *
	 * @uses   $this->excluded_post_types
	 * @param  string $post_type
	 * @return boolean
	 */
	protected function _filter_post_types( $post_type ) {
		return ! in_array( $post_type, $this->excluded_post_types, true );
	}

	/**
	 * Get parent menu slug for the specified custom post type
	 *
	 * @param  string $post_type
	 * @return string            URL path and query string for specified post type
	 */
	protected function _get_parent_slug( $post_type ) {
		$parent_slug = 'edit.php';
		if ( 'post' !== $post_type ) $parent_slug .= "?post_type={$post_type}";

		return $parent_slug;
	}

	/**
	 * Get singular name of specified post type
	 *
	 * @param  string $post_type
	 * @return string            Singular label of specified post type
	 */
	protected function _get_post_type_label( $post_type ) {
		$post_type_obj = get_post_type_object($post_type);

		return $post_type_obj->labels->singular_name;
	}

}

new Archive_Pages();


// Global functions

/**
 * Returns wp-admin edit link for archive page of specified post type
 *
 * @param  string $post_type
 * @return string            wp-admin edit link for specified post type
 */
function get_edit_archive_page_link( $post_type ) {
	$post = get_post_type_archive_page( $post_type );
	$url = get_edit_post_link( $post->ID );

	return $url;
}

/**
 * Get the archive page for the specified post type
 *
 * @param  string $post_type
 * @return WP_Post            Post object of archive page for the specified post type
 */
function get_post_type_archive_page( $post_type ) {
	$archive_page = false;

	$posts = get_posts(
		array(
			'post_type' => Archive_Pages::POST_TYPE,
			'meta_query' => array(
				array(
					'key'     => Archive_Pages::META_KEY,
					'value'   => $post_type
				),
			),
		)
	);

	if ( count($posts) ) {
		$archive_page = reset( $posts );
	}

	return $archive_page;
}

add_action( 'admin_init', function () {

	if ( ! class_exists('WP_GitHub_Updater') ) {
		include_once( 'WordPress-GitHub-Plugin-Updater/updater.php' );
	}

	define( 'WP_GITHUB_FORCE_UPDATE', true );

	$config = array(
		'slug' => plugin_basename(__FILE__), // this is the slug of your plugin
		'proper_folder_name' => 'wp-archive-pages', // this is the name of the folder your plugin lives in
		'api_url' => 'https://api.github.com/repos/creativecoder/wp-archive-pages', // the GitHub API url of your GitHub repo
		'raw_url' => 'https://raw.github.com/creativecoder/wp-archive-pages/master', // the GitHub raw url of your GitHub repo
		'github_url' => 'https://github.com/creativecoder/wp-archive-pages', // the GitHub url of your GitHub repo
		'zip_url' => 'https://github.com/creativecoder/wp-archive-pages/zipball/master', // the zip url of the GitHub repo
		'sslverify' => true, // whether WP should check the validity of the SSL cert when getting an update, see https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/2 and https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/4 for details
		'requires' => '3.0', // which version of WordPress does your plugin require?
		'tested' => '4.4', // which version of WordPress is your plugin tested up to?
		'readme' => 'README.md', // which file to use as the readme for the version number
		'access_token' => '', // Access private repositories by authorizing under Appearance > GitHub Updates when this example plugin is installed
	);

	new WP_GitHub_Updater( $config );
});
