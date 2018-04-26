<?php
/**
 * @package   Multisite_Posts_Counter
 * @author    David Arceneaux <david@davidthemachine.org>
 * @license   GPL-2.0+
 * @link      http://example.com
 * @copyright 2018 David Arceneaux
 *
 * @wordpress-plugin
 * Plugin Name:       Multisite Posts Counter
 * Plugin URI:        @TODO
 * Description:       @TODO
 * Version:           1.0.0
 * Author:            David Arceneaux <david@davidthemachine.org>
 * Author URI:        https://davidthemachine.org
 * Text Domain:       multisite-posts-counter
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * PHP Version:       7.2
 * Domain Path:       /lang
 * GitHub Plugin URI: https://github.com/darcmattr/<repo>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Built with use of https://github.com/tommcfarlin/WordPress-Widget-Boilerplate
 */
class Multisite_Posts_Counter extends WP_Widget {
	/**
	 * The Widget Slug value.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const WIDGET_SLUG = 'multisite-posts-counter';

	/**
	 * The time in seconds that the widget refreshes.
	 *
	 * Used for cache expiration and RESTful widget refreshing.
	 *
	 * @var int
	 */
	const REFRESH_INTERVAL = 60;

	/**
	 * Specifies the classname and description, instantiates the widget,
	 * loads localization files, and includes necessary stylesheets and JavaScript.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'widget_textdomain' ] );

		parent::__construct(
			$this->get_widget_slug(),
			__( 'Multisite Posts Counter', $this->get_widget_slug() ),
			[
				'classname'   => $this->get_widget_slug() . '-class',
				'description' => __( 'Display post and user counts for all sites in network.', $this->get_widget_slug() ),
			]
		);

		// Register admin styles and scripts.
		add_action( 'admin_print_styles', [ $this, 'register_admin_styles' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'register_admin_scripts' ] );

		// Register site styles and scripts.
		add_action( 'wp_enqueue_scripts', [ $this, 'register_widget_styles' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_widget_scripts' ] );

		// Refreshing the widget's cached output with each new post.
		add_action( 'save_post', [ $this, 'flush_widget_cache' ] );
		add_action( 'deleted_post', [ $this, 'flush_widget_cache' ] );
		add_action( 'switch_theme', [ $this, 'flush_widget_cache' ] );
	}

	/**
	 * Get information for all public sites in a network.
	 *
	 * Return number of posts, users, URL.
	 *
	 * @param int $site_id Network Site ID for site.
	 *
	 * @return array
	 */
	public static function get_site_info( int $site_id = 0 ) : array {
		$query = [
			'public' => '1',
		];

		$out_sites = wp_cache_get( SELF::WIDGET_SLUG, 'rest' );

		if ( ! is_array( $out_sites ) ) {
			$out_sites = [];
		} else {
			return $out_sites;
		}

		if ( 0 !== $site_id ) {
			$query['site__in'] = $site_id;
		}

		$sites = get_sites( $query );

		if ( is_array( $sites ) ) {
			foreach ( $sites as $site ) {
				$blog_id = $site->blog_id;

				$user_query                          = new \WP_User_Query( [ 'blog_id' => $blog_id ] );
				$out_sites[ $blog_id ]['user_count'] = $user_query->get_total();

				// If this must be hosted on a WP-VIP site, then rewrite to use REST API.
				switch_to_blog( $blog_id );
				$out_sites[ $blog_id ]['url']  = site_url();
				$out_sites[ $blog_id ]['name'] = get_option( 'blogname' );

				$posts                               = new \WP_Query( 'post_status=published' );
				$out_sites[ $blog_id ]['post_count'] = intval( $posts->found_posts );
			}
			restore_current_blog();
		}

		wp_cache_set(
			self::WIDGET_SLUG,
			$out_sites,
			'rest',
			self::REFRESH_INTERVAL
		);

		return $out_sites;
	}

	/**
	 * Return the widget slug.
	 *
	 * @since    1.0.0
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_widget_slug() {
		return self::WIDGET_SLUG;
	}

	/**
	 * Outputs the content of the widget.
	 *
	 * @param array args  The array of form elements.
	 * @param array instance The current instance of the widget.
	 */
	public function widget( $args, $instance ) {
		// Check if there is a cached output
		$widget_cache = wp_cache_get( $this->get_widget_slug(), 'widget' );
		$info_cache   = wp_cache_get( $this->get_widget_slug(), 'info' );

		if ( ! is_array( $widget_cache ) ) {
			$widget_cache = [];
		}

		if ( ! is_array( $info_cache ) ) {
			$info_cache = self::get_site_info();
		}

		if ( ! isset( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}

		if ( isset( $widget_cache[ $args['widget_id'] ] ) ) {
			echo $widget_cache[ $args['widget_id'] ]; // WPCS: XSS ok.
			return;
		}

		$title = apply_filters( 'widget_title', $args['title'] );

		$widget_string = $args['before_widget'];

		$widget_string .= <<<EOL
			{$args['before_title']}{$title}{$args['after_title']}
			<dl class="mpc-list">
EOL;

		foreach ( $info_cache as $blog_id => $info ) {
			$widget_string .= <<<EOL
	<dt data-blog_id='{$blog_id}' class='mpc-list-site'>Site</dt>
	<dd data-blog_id='{$blog_id}' class='mpc-list-site-item'>
		<a class='mpc-list-site-item-link' href='{$info['url']}'>
			{$info['name']}
		</a>
	</dd>
	<dt data-blog_id='{$blog_id}' class="mpc-list-posts">Posts</dt>
	<dd data-blog_id='{$blog_id}' class='mpc-list-posts-item'>{$info['post_count']}</dd>
	<dt data-blog_id='{$blog_id}' class='mpc-list-users'>Users</dt>
	<dd data-blog_id='{$blog_id}' class='mpc-list-users-item'>{$info['user_count']}</dd>
EOL;
		}
		$widget_string .= '</dl>' . "\n";

		$widget_string .= $args['after_widget'];

		$cache[ $args['widget_id'] ] = $widget_string;

		wp_cache_set(
			$this->get_widget_slug(),
			$cache,
			'widget',
			self::REFRESH_INTERVAL
		);

		echo $widget_string; // WPCS: XSS ok.
	}


	/**
	 * Clears out wp_cache entries for widget markup.
	 */
	public function flush_widget_cache() {
		wp_cache_delete( $this->get_widget_slug(), 'widget' );
		wp_cache_delete( $this->get_widget_slug(), 'rest' );
	}

	/**
	 * Processes the widget's options to be saved.
	 *
	 * @param array new_instance The new instance of values to be generated via the update.
	 * @param array old_instance The previous instance of values before the update.
	 */
	public function update( $new_instance, $old_instance ) {

		$instance = $old_instance;

		// TODO: Here is where you update your widget's old values with the new, incoming values
		return $instance;

	}

	/**
	 * Generates the administration form for the widget.
	 *
	 * @param array instance The array of keys and values for the widget.
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance );

		if ( isset( $instance['title'] ) ) {
			$title = $instance['title'];
		} else {
			$title = __( 'New Title', $this->get_widget_slug() );
		}

		$widget_form = <<<EOL
EOL;

		echo $widget_form;
	}

	/**
	 * Loads the Widget's text domain for localization and translation.
	 */
	public function widget_textdomain() : void {
		load_plugin_textdomain(
			$this->get_widget_slug(),
			false,
			dirname( plugin_basename( __FILE__ ) ) . 'lang/'
		);
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @param  boolean $network_wide True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog.
	 */
	public static function activate( $network_wide ) {
		// TODO define activation functionality here
	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @param boolean $network_wide True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog
	 */
	public static function deactivate( $network_wide ) {
		// TODO define deactivation functionality here
	}

	/**
	 * Registers and enqueues admin-specific styles.
	 */
	public function register_admin_styles() : void {
		wp_enqueue_style(
			$this->get_widget_slug() . '-admin-styles',
			plugins_url( 'css/admin.css', __FILE__ )
		);
	}

	/**
	 * Registers and enqueues admin-specific JavaScript.
	 */
	public function register_admin_scripts() : void {
		wp_enqueue_script(
			$this->get_widget_slug() . '-admin-script',
			plugins_url( 'js/admin.js', __FILE__ ),
			[ 'jquery' ]
		);
	}

	/**
	 * Registers and enqueues widget-specific styles.
	 */
	public function register_widget_styles() : void {
		wp_enqueue_style(
			$this->get_widget_slug() . '-widget-styles',
			plugins_url( 'css/widget.css', __FILE__ )
		);
	}

	/**
	 * Registers and enqueues widget-specific scripts.
	 */
	public function register_widget_scripts() : void {
		wp_enqueue_script(
			$this->get_widget_slug() . '-script',
			plugins_url( 'js/widget.js', __FILE__ ),
			[ 'jquery' ]
		);
	}

	/**
	 * Registers widget with WordPress.
	 */
	public static function register_widget() : void {
		register_widget( __CLASS__ );
	}
}

add_action( 'widgets_init', [ 'Multisite_Posts_Counter', 'register_widget' ] );

register_activation_hook( __FILE__, [ 'Multisite_Posts_Counter', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Multisite_Posts_Counter', 'deactivate' ] );
