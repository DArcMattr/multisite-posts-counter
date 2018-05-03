<?php
/**
 * Contains class definition for Multisite_Posts_Counter.
 *
 * @package   Multisite_Posts_Counter
 * @author    David Arceneaux <david@davidthemachine.org>
 * @license   GPL-2.0+
 * @link      https://github.com/darcmattr/multisite-posts-counter
 * @copyright 2018 David Arceneaux
 */

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
	 * The plugin version
	 *
	 * @since    1.0.1
	 * @var      string
	 */
	const VERSION = '1.0.1';

	/**
	 * Endpoint version.
	 *
	 * @var string
	 */
	const ENDPOINT_VERSION = 'v1';

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
			self::WIDGET_SLUG,
			__( 'Multisite Posts Counter', 'multisite-posts-counter' ),
			[
				'classname'   => self::WIDGET_SLUG . '-class',
				'description' => __( 'Display post and user counts for all sites in network.', 'multisite-posts-counter' ),
			]
		);

		// Refreshing the widget's cached output with each new post.
		add_action( 'add_role', [ $this, 'flush_widget_cache' ] );
		add_action( 'deleted_post', [ $this, 'flush_widget_cache' ] );
		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
		add_action( 'save_post', [ $this, 'flush_widget_cache' ] );
		add_action( 'switch_theme', [ $this, 'flush_widget_cache' ] );
	}

	/**
	 * Hook into REST API
	 */
	public function rest_api_init() : void {
		register_rest_route(
			self::WIDGET_SLUG . '/' . self::ENDPOINT_VERSION,
			'/site(:?/(?P<blog_id>\d+))?',
			[
				'methods'  => WP_REST_Server::READABLE,
				'callback' => function( WP_REST_Request $request ) : array {
					$blog_id = intval( $request->get_param( 'blog_id' ) );
					return self::get_site_info( $blog_id );
				},
				'args'     => [
					'blog_id' => [
						'type'              => 'integer',
						'validate_callback' => function( $param ) {
							if ( false !== get_site( $param ) ) {
								return is_numeric( $param );
							}
						},
					],
				],
			]
		);
	}

	/**
	 * Get information for all public sites in a network.
	 *
	 * Return number of posts, users, URL.
	 *
	 * @param int $blog_id Network Site ID for site.
	 *
	 * @return array
	 */
	public static function get_site_info( int $blog_id = 0 ) : array {
		$query = [
			'public' => '1',
		];

		$out_sites = wp_cache_get( self::WIDGET_SLUG, 'rest' );

		if ( ! is_array( $out_sites ) ) {
			$out_sites = [];
		} else {
			return $out_sites;
		}

		if ( 0 !== $blog_id ) {
			$query['site__in'] = intval( $blog_id );
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
	 * Outputs the content of the widget.
	 *
	 * @param array $args     The array of form elements.
	 * @param array $instance The current instance of the widget.
	 */
	public function widget( $args, $instance ) {
		$widget_markup_cache = wp_cache_get( self::WIDGET_SLUG, 'widget' );
		$site_info_cache     = wp_cache_get( self::WIDGET_SLUG, 'info' );

		$refresh_interval = self::REFRESH_INTERVAL;
		$endpoint         = self::WIDGET_SLUG . '/' . self::ENDPOINT_VERSION . '/site/';

		if ( ! is_array( $widget_markup_cache ) ) {
			$widget_markup_cache = [];
		}

		if ( ! is_array( $site_info_cache ) ) {
			$site_info_cache = self::get_site_info();
		}

		if ( ! isset( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}

		if (
			! is_customizer_preview() &&
			isset( $widget_markup_cache[ $args['widget_id'] ] )
		) {
			echo $widget_markup_cache[ $args['widget_id'] ]; // WPCS: XSS ok.
			return;
		}

		$title = apply_filters( 'widget_title', $instance['title'] );

		$widget_string = $args['before_widget'];

		$widget_string .= <<<EOL
			{$args['before_title']}{$title}{$args['after_title']}
<ul data-endpoint='{$endpoint}' data-refresh='{$refresh_interval}' class='mpc-list'>
EOL;

		foreach ( $site_info_cache as $blog_id => $info ) {
			$url        = esc_url( $info['url'] );
			$name       = esc_html( $info['name'] );
			$post_count = intval( $info['post_count'] );
			$user_count = intval( $info['user_count'] );

			$widget_string .= <<<EOL
	<li data-blog_id='{$blog_id}' class='mpc-list-item'>
		<a class='mpc-list-item-link' href='{$url}'>{$name}</a>, with
		<span class='mpc-list-item-posts'>{$post_count}</span> posts and
		<span class='mpc-list-item-users'>{$user_count}</span> users
	</li>
EOL;
		}
		$widget_string .= '</ul>' . "\n";

		$widget_string .= $args['after_widget'];

		$cache[ $args['widget_id'] ] = $widget_string;

		if ( ! is_customizer_preview() ) {
			wp_cache_set(
				self::WIDGET_SLUG,
				$cache,
				'widget',
				self::REFRESH_INTERVAL
			);
		}

		echo $widget_string; // WPCS: XSS ok.
	}


	/**
	 * Clears out wp_cache entries for widget markup.
	 */
	public static function flush_widget_cache() {
		wp_cache_delete( self::WIDGET_SLUG, 'widget' );
		wp_cache_delete( self::WIDGET_SLUG, 'rest' );
	}

	/**
	 * Processes the widget's options to be saved.
	 *
	 * @param array $new_instance The new instance of values to be generated via the update.
	 * @param array $old_instance The previous instance of values before the update.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = [];

		$instance['title'] = ! empty( $new_instance['title'] ) ?
			sanitize_text_field( $new_instance['title'] ) :
			__( 'New Title', 'multisite-posts-counter' );

		return $instance;
	}

	/**
	 * Generates the administration form for the widget.
	 *
	 * @param array $instance The array of keys and values for the widget.
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance );

		if ( isset( $instance['title'] ) ) {
			$title = $instance['title'];
		} else {
			$title = __( 'New Title', 'multisite-posts-counter' );
		}

		$widget_title_id   = esc_attr( $this->get_field_id( 'title' ) );
		$widget_title_name = esc_html( $this->get_field_name( 'title' ) );
		$widget_title      = __( 'Title:', 'multisite-posts-counter' );

		$widget_form = <<<EOL
<p>
	<label for='{$widget_title_id}'>{$widget_title}</label>
	<input type='text' class='widefat' id='{$widget_title_id}' name='{$widget_title_name}' value='{$title}'>
</p>
EOL;

		echo $widget_form; // WPCS: XSS ok.
	}

	/**
	 * Loads the Widget's text domain for localization and translation.
	 */
	public function widget_textdomain() : void {
		load_plugin_textdomain(
			self::WIDGET_SLUG,
			false,
			dirname( plugin_basename( __FILE__ ) ) . 'lang/'
		);
	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @param boolean $network_wide True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog.
	 */
	public function deactivate( $network_wide ) {
		$this->flush_widget_cache();
	}

	/**
	 * Registers and enqueues widget-specific styles.
	 */
	public static function register_widget_styles() : void {
		wp_enqueue_style(
			self::WIDGET_SLUG . '-widget-styles',
			plugins_url( 'css/widget.css', __DIR__ ),
			[],
			self::VERSION,
			'all'
		);
	}

	/**
	 * Registers and enqueues widget-specific scripts.
	 */
	public static function register_widget_scripts() : void {
		wp_enqueue_script(
			self::WIDGET_SLUG . '-script',
			plugins_url( 'js/widget.js', __DIR__ ),
			[ 'jquery' ],
			self::VERSION,
			true
		);
	}

	/**
	 * Registers widget with WordPress.
	 */
	public static function register_widget() : void {
		register_widget( __CLASS__ );

		// Register site styles and scripts.
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_widget_styles' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_widget_scripts' ] );
	}
}
