<?php
/*
Plugin Name: wp-autositemap
Plugin URI: https://github.com/petermolnar/wp-autositemap
Description:
Version: 0.1
Author: Peter Molnar <hello@petermolnar.net>
Author URI: http://petermolnar.net/
License: GPLv3
*/

/*  Copyright 2016 Peter Molnar ( hello@petermolnar.net )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 3, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace WP_AUTOSITEMAP;

\register_activation_hook( __FILE__ , '\WP_AUTOSITEMAP\plugin_activate' );
\register_deactivation_hook( __FILE__ , '\WP_AUTOSITEMAP\plugin_deactivate' );

// init all the things!
\add_action( 'init', '\WP_AUTOSITEMAP\init' );

// register new posts
\add_action(
	"transition_post_status",
	'\WP_AUTOSITEMAP\trigger_sitemap_cron',
	1,
	3
);

// cron based export for all posts
\add_action( 'wp_autositemap', '\WP_AUTOSITEMAP\generate_sitemap' );

/**
 * activate hook
 */
function plugin_activate() {
	if ( version_compare( phpversion(), 5.4, '<' ) ) {
		die( 'The minimum PHP version required for this plugin is 5.3' );
	}
}

/**
 *
 */
function plugin_deactivate() {
		wp_unschedule_event( time(), 'wp_autositemap' );
		wp_clear_scheduled_hook( 'wp_autositemap' );
}

/**
 *
 */
function init () {

	if ( ! wp_get_schedule( 'wp_autositemap' ))
		wp_schedule_event ( time(), 'daily', 'wp_autositemap' );

}

function trigger_sitemap_cron ( $new_status = null , $old_status = null,
	$post = null ) {
	if (  null === $new_status || null === $old_status || null === $post )
		return;

	wp_schedule_single_event( time() + 1, 'wp_autositemap' );
}

/**
 *
 */
function generate_sitemap () {
	$head = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">';

	$foot = '</urlset>';

	$tpl = "    <url>\n"
		. "        <loc>%s</loc>\n"
		. "        <lastmod>%s</lastmod>\n"
		. "        <changefreq>%s</changefreq>\n"
		. "        <priority>%d</priority>\n"
	  . "    </url>";

		global $wpdb;

		$posts = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_password = '' ORDER BY post_type DESC, post_date DESC" );

		foreach ( $posts as $post ) {
			$url = get_permalink( $post->ID );
			$pubdate = get_the_time( 'c', $post->ID );

			$e[] = sprintf ( $tpl, $url, $pubdate, 'weekly', 1 );
		}

		$out = $head . "\n" . join( "\n", $e ) . "\n" . $foot;
		$smapf = get_home_path() .  DIRECTORY_SEPARATOR . 'sitemap.xml';

		file_put_contents( $smapf, $out );
}

/**
 *
 * debug messages; will only work if WP_DEBUG is on
 * or if the level is LOG_ERR, but that will kill the process
 *
 * @param string $message
 * @param int $level
 *
 * @output log to syslog | wp_die on high level
 * @return false on not taking action, true on log sent
 */
function debug( $message, $level = LOG_NOTICE ) {
	if ( empty( $message ) )
		return false;

	if ( @is_array( $message ) || @is_object ( $message ) )
		$message = json_encode($message);

	$levels = array (
		LOG_EMERG => 0, // system is unusable
		LOG_ALERT => 1, // Alert 	action must be taken immediately
		LOG_CRIT => 2, // Critical 	critical conditions
		LOG_ERR => 3, // Error 	error conditions
		LOG_WARNING => 4, // Warning 	warning conditions
		LOG_NOTICE => 5, // Notice 	normal but significant condition
		LOG_INFO => 6, // Informational 	informational messages
		LOG_DEBUG => 7, // Debug 	debug-level messages
	);

	// number for number based comparison
	// should work with the defines only, this is just a make-it-sure step
	$level_ = $levels [ $level ];

	// in case WordPress debug log has a minimum level
	if ( defined ( '\WP_DEBUG_LEVEL' ) ) {
		$wp_level = $levels [ \WP_DEBUG_LEVEL ];
		if ( $level_ > $wp_level ) {
			return false;
		}
	}

	// ERR, CRIT, ALERT and EMERG
	if ( 3 >= $level_ ) {
		\wp_die( '<h1>Error:</h1>' . '<p>' . $message . '</p>' );
		exit;
	}

	$trace = debug_backtrace();
	$caller = $trace[1];
	$parent = $caller['function'];

	if (isset($caller['class']))
		$parent = $caller['class'] . '::' . $parent;

	return error_log( "{$parent}: {$message}" );
}
