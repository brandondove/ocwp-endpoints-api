<?php
/*
Plugin Name: CPT Archive to Nav
Plugin URI: https://github.com/klangley/cpt-archive-to-nav
Description: Gives the user the ability to add the archive of custom post types to a menu
Author: Kevin Langley
Version: 0.2
Author URI: http://profiles.wordpress.org/users/kevinlangleyjr
*******************************************************************
Copyright 2011-2012 Kevin Langley (email : me@ubergeni.us && klangley@voceconnect.com)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*******************************************************************
*/

class CPT_Archive_To_Nav {
	public static function init() {
		add_action( 'admin_head-nav-menus.php', array( __CLASS__, 'add_filters' ) );
		add_filter( 'nav_menu_css_class', array(__CLASS__, 'fix_classes'), 10, 3 );
	}

	public static function add_filters() {
		$post_type_args = array(
			'show_in_nav_menus' => true
		);

		$post_types = get_post_types( $post_type_args, 'object' );

		foreach ( $post_types as $post_type ) {
			if ( $post_type->has_archive ) {
				add_filter( 'nav_menu_items_' . $post_type->name, array( __CLASS__, 'add_archive_checkbox' ), null, 3 );
			}
		}
	}

	public static function add_archive_checkbox( $posts, $args, $post_type ) {
		global $_nav_menu_placeholder, $wp_rewrite;
		$_nav_menu_placeholder = ( 0 > $_nav_menu_placeholder ) ? intval($_nav_menu_placeholder) - 1 : -1;

		array_unshift( $posts, (object) array(
			'ID' => 0,
			'object_id' => $_nav_menu_placeholder,
			'post_content' => '',
			'post_excerpt' => '',
			'post_title' => $post_type['args']->labels->singular_name.' Archive',
			'post_name' => $post_type['args']->name,
			'post_type' => 'nav_menu_item',
			'type' => 'custom',
			'url' => get_post_type_archive_link($args['post_type'])
		) );

		return $posts;
	}
	
	public static function fix_classes($classes, $item, $args){
		global $wp_query;
		$post_type = $wp_query->query_vars['post_type'];
		$posts_page = get_option('page_for_posts', true);
		if($item->object_id == $posts_page && ($post_type != 'post' && $post_type != '')){
			$remove_array = array('current_page_parent', 'current_page_item', 'current-menu-item');
			foreach($remove_array as $remove){
				$class_index = array_search($remove, $classes);
				if($class_index){
					unset($classes[$class_index]);
				}
			}
			
		}
		if($post_type != ''){
			$post_type_url = get_post_type_archive_link($post_type);
			$check = strpos($post_type_url, $item->url);
			if( $check !== false && $check == 0 && $item->url != trailingslashit( site_url() ) ){
				$classes[] = 'current_page_parent';
				$classes[] = 'current_page_item';
				$classes[] = 'current-menu-item';
			}
		}
		return $classes;
	}
}
add_action( 'init', array( 'CPT_Archive_To_Nav', 'init' ) );
