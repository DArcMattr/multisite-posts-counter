<?php
/**
 * A Widget that shows (multi)site stats.
 *
 * @package   Multisite_Posts_Counter
 * @author    David Arceneaux <david@davidthemachine.org>
 * @license   GPL-2.0+
 * @link      https://github.com/darcmattr/multisite-posts-counter
 * @copyright 2018 David Arceneaux
 *
 * @wordpress-plugin
 * Plugin Name:       Multisite Posts Counter
 * Plugin URI:        https://github.com/darcmattr/multisite-posts-counter
 * Description:       A widget that shows (multi)site stats.
 * Version:           1.0.1
 * Author:            David Arceneaux <david@davidthemachine.org>
 * Author URI:        https://davidthemachine.org
 * Text Domain:       multisite-posts-counter
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * PHP Version:       7.2
 * Domain Path:       /lang
 * GitHub Plugin URI: https://github.com/darcmattr/multisite-posts-counter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'includes/class-multisite-posts-counter.php';

add_action( 'widgets_init', [ 'Multisite_Posts_Counter', 'register_widget' ] );

register_deactivation_hook( __FILE__, [ 'Multisite_Posts_Counter', 'deactivate' ] );
