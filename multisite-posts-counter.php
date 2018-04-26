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
	protected $widget_slug = 'multisite-posts-counter';

	/**
	 * Specifies the classname and description, instantiates the widget,
	 * loads localization files, and includes necessary stylesheets and JavaScript.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'widget_textdomain' ] );

		parent::__construct(
			$this->get_widget_slug(),
			__( 'Widget Name', $this->get_widget_slug() ),
			[
				'classname'   => $this->get_widget_slug() . '-class',
				'description' => __( 'Short description of the widget goes here.', $this->get_widget_slug() ),
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
	public function get_site_posts( int $site_id = 0 ) : array {
		$out_site = [];
		$query    = [
			'public' => '1',
		];

		if ( 0 === $site_id ) {
			$sites = get_sites( $query );
		} else {
			$query['site__in'] = $site_id;
			$sites             = get_sites( $query );
		}

		foreach ( $sites as $site ) {
			if ( '1' === $site->public ) {
				$out_site['site_id'] = $site->site_id;
				$out_site['url']     = $site->domain . $site->path;
			}
		}

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
		return $this->widget_slug;
	}

	/**
	 * Outputs the content of the widget.
	 *
	 * @param array args  The array of form elements.
	 * @param array instance The current instance of the widget.
	 */
	public function widget( $args, $instance ) {
		// Check if there is a cached output
		$cache = wp_cache_get( $this->get_widget_slug(), 'widget' );

		if ( ! is_array( $cache ) ) {
			$cache = [];
		}

		if ( ! isset( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}

		if ( isset( $cache[ $args['widget_id'] ] ) ) {
			return print $cache[ $args['widget_id'] ];
		}

		// Go on with your widget logic, put everything into a string and …
		extract( $args, EXTR_SKIP );

		$widget_string = $before_widget;

		// TODO: Here is where you manipulate your widget's values based on their input fields
		ob_start();

		include plugin_dir_path( __FILE__ ) . 'views/widget.php';
		$widget_string .= ob_get_clean();
		$widget_string .= $after_widget;

		$cache[ $args['widget_id'] ] = $widget_string;

		wp_cache_set( $this->get_widget_slug(), $cache, 'widget' );

		print $widget_string;
	}


	/**
	 * Clears out wp_cache entries for widget markup.
	 */
	public function flush_widget_cache() {
		wp_cache_delete( $this->get_widget_slug(), 'widget' );
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

		// TODO: Define default values for your variables
		$instance = wp_parse_args(
			(array) $instance
		);

		// TODO: Store the values of the widget in their own variable
		// Display the admin form
		include plugin_dir_path( __FILE__ ) . 'views/admin.php';
	}

	/**
	 * Loads the Widget's text domain for localization and translation.
	 */
	public function widget_textdomain() : void {
		load_plugin_textdomain( $this->get_widget_slug(), false, dirname( plugin_basename( __FILE__ ) ) . 'lang/' );
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
		wp_enqueue_style( $this->get_widget_slug() . '-admin-styles', plugins_url( 'css/admin.css', __FILE__ ) );
	}

	/**
	 * Registers and enqueues admin-specific JavaScript.
	 */
	public function register_admin_scripts() : void {
		wp_enqueue_script( $this->get_widget_slug() . '-admin-script', plugins_url( 'js/admin.js', __FILE__ ), [ 'jquery' ] );
	}

	/**
	 * Registers and enqueues widget-specific styles.
	 */
	public function register_widget_styles() : void {
		wp_enqueue_style( $this->get_widget_slug() . '-widget-styles', plugins_url( 'css/widget.css', __FILE__ ) );
	}

	/**
	 * Registers and enqueues widget-specific scripts.
	 */
	public function register_widget_scripts() : void {
		wp_enqueue_script( $this->get_widget_slug() . '-script', plugins_url( 'js/widget.js', __FILE__ ), [ 'jquery' ] );
	}

	/**
	 * Registers widget with WordPress.
	 */
	public function register_widget() : void {
		register_widget( 'Multisite_Posts_Counter' );
	}
}

add_action( 'widgets_init', [ 'Multisite_Posts_Counter', 'register_widget' ] );

register_activation_hook( __FILE__, [ 'Multisite_Posts_Counter', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Multisite_Posts_Counter', 'deactivate' ] );
