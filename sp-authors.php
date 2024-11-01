<?php
/*
Plugin Name: SP Authors
Description: Allows multiple authors to be assigned to a post or page.
Version: 1.0
Author: leticia5959,gpc

GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
*/

$avatar_size = 40;

global $wp_version;
$exit_msg='SP Authors for WordPress requires WordPress 2.6 or  
         newer. <a href="http://codex.wordpress.org/Upgrading_ 
         WordPress">Please update!</a>';
if (version_compare($wp_version,"2.6","<"))
{
    exit ($exit_msg);
}
  
$plugin_url = trailingslashit( WP_PLUGIN_URL.'/'.dirname( plugin_basename(__FILE__) ));
$plugin_meta_data_name = '_sp_authors';

// print scripts action

add_action('admin_print_scripts-post.php',  'sp_authors_scripts_action');
add_action('admin_print_scripts-page.php',  'sp_authors_scripts_action');
add_action('admin_print_scripts-post-new.php',  'sp_authors_scripts_action');
add_action('admin_print_scripts-page-new.php',  'sp_authors_scripts_action');

function sp_authors_scripts_action() {
	
	global $plugin_url;
	
	wp_enqueue_script('sp-authors', $plugin_url. 
          '/sp-authors.js', array('jquery'));
}

// End: print scripts action

// Draw Authors Panel

function sp_authors_draw_panel()  
{
	global $plugin_meta_data_name, $wpdb, $post;
	
	$current_user = wp_get_current_user();
	
	$co_authors_arr = get_post_meta($post->ID, $plugin_meta_data_name);
	$result_arr = array();
	
	if(!empty($co_authors_arr)){
		if(!is_array($co_authors_arr))
			$co_authors_arr = array($co_authors_arr);
		foreach ($co_authors_arr as $co_author) {
			$author_name = get_author_name($co_author);
			$result_arr[] = $author_name;
		}
	}
	
	echo '<input type="text" value="'.join(', ',$result_arr).'" id="'. $plugin_meta_data_name .'" name="'. $plugin_meta_data_name .'" />';
	
	echo '<select name="sp_authors_all_users">';
	
	$users = $wpdb->get_results("SELECT display_name FROM $wpdb->users
		where user_login!='$current_user->user_login'
	");
		
	if ($users)
	{
		foreach ($users as $user)  
	    {
	    	echo "<option value='$user->display_name'>$user->display_name</option>";
	    }
	}
	echo '</select>';
	
	echo '<input id="wp-authors-submit" class="button" type="button"  
        value="Add"  onclick="ChooseMe(this.form.sp_authors_all_users,this.form.'. $plugin_meta_data_name .')" />';
}

function sp_authors_admin_menu() {
	// custom panel for edit post
    add_meta_box( 'sp_authors', 'Co-Authors', 'sp_authors_draw_panel', 'post', 'normal', 'high' );

    // custom panel for edit page
    add_meta_box( 'sp_authors', 'Co-Authors', 'sp_authors_draw_panel', 'page', 'normal', 'high' );
}

add_action('admin_menu',  'sp_authors_admin_menu');

// End: Draw Authors Panel

// Save meta data to post and pages

add_action('save_post', 'sp_authors_save_post_meta',11,2);
add_action('save_page', 'sp_authors_save_post_meta',11,2);

function sp_authors_save_post_meta($new_post_id, $post) {
	
	global $plugin_meta_data_name, $wpdb, $post;
	
	// Ignore autosaves, ignore quick saves
	if (@constant( 'DOING_AUTOSAVE')) return $post;
	if (!$_POST) return $post;
	if (!in_array($_POST['action'], array('editpost', 'post'))) return $post;

	$post_id = attribute_escape($_POST['post_ID']);
	if (!$post_id) $post_id = $new_post_id;
	if (!$post_id) return $post;
		
	// Make sure we're saving the correct version
	if ( $p = wp_is_post_revision($post_id)) $post_id = $p;
	
	// Save co-authors
	if ($co_authors = attribute_escape($_POST[$plugin_meta_data_name])) {
		if (!$co_authors && (!get_post_meta($post_id, $plugin_meta_data_name, true))) {
			// Do nothing
		} else {
			//Delete all existing co-authors from a post
			delete_post_meta($post_id, $plugin_meta_data_name);
			
			$co_authors_arr = split(',',$co_authors);
			
			foreach ($co_authors_arr as $co_author) {
				$author_id = _get_author_id_by_display(trim($co_author));
				add_post_meta($post_id, $plugin_meta_data_name, (int)$author_id, false);
			}
		}
	}
}

// End: Save meta data to post

// add shortcode handler

function _get_author_id_by_display($login) {

	global $wpdb;
	
	$user_id = $wpdb->get_var("SELECT ID FROM $wpdb->users
	where display_name='$login'
	");
	
	return $user_id;
}

function _get_author_id_by_login($login) {
	
	global $wpdb;
	
	$user_id = $wpdb->get_var("SELECT ID FROM $wpdb->users
		where user_login='$login'
	");
		
	return $user_id;
}

/**
 * Display or retrieve the current co authors.
 */

function sp_authors_display($before = '', $after = '', $echo = true) {
	global $post, $plugin_meta_data_name;
	
	$post_id = $post->ID;
	
	$co_authors_arr = get_post_meta($post_id, $plugin_meta_data_name);
	$result = $before;
	$result_arr = array();
	
	if(!empty($co_authors_arr)){
		if(!is_array($co_authors_arr))
			$co_authors_arr = array($co_authors_arr);
		foreach ($co_authors_arr as $co_author) {
			$author_name = get_author_name($co_author);
			$result_arr[] = '<a href="'.get_author_posts_url( $co_author, $author_name ).
				'" title="'.$author_name.'">'.$author_name.'</a>';
		}
	}

	$result .= join(', ',$result_arr).$after;	
	
	if ( $echo )
		echo $result;
	else
		return $result;
}

function sp_authors_photos_display($before = '', $after = '', $echo = true) {
	global $post, $plugin_meta_data_name, $avatar_size;
	
	$post_id = $post->ID;
	
	$co_authors_arr = get_post_meta($post_id, $plugin_meta_data_name);
	$result = $before;
	$result_arr = array();
	
	if(!empty($co_authors_arr)){
		if(!is_array($co_authors_arr))
			$co_authors_arr = array($co_authors_arr);
		foreach ($co_authors_arr as $co_author) {
			$author_name = get_author_name($co_author);
			$result_arr[] = '<a href="'.get_author_posts_url( $co_author, $author_name ).
				'" title="'.$author_name.'">'.get_avatar($co_author,$avatar_size).'</a>';
		}
	}

	$result .= join(' ',$result_arr).$after;	
	
	if ( $echo )
		echo $result;
	else
		return $result;
}

function sp_authors_shortcode() {
	return sp_authors_display('', '', FALSE);
}


add_shortcode('sp-authors', 'sp_authors_shortcode');

// End: add shortcode handler

// Search of posts
//Modify the author query posts SQL to include posts co-authored
function sp_authors_posts_join_filter($join){
	global $wpdb,$wp_query,$plugin_meta_data_name;
	if(is_author()){
		$join .= " LEFT JOIN $wpdb->postmeta ON $wpdb->postmeta.post_id = $wpdb->posts.id "
		       . "AND $wpdb->postmeta.meta_key = '$plugin_meta_data_name' "
			   . "AND $wpdb->postmeta.meta_value = '" . $wp_query->query_vars['author'] . "' "; //this condition removes need for DISTINCT
	}
	return $join;
}
add_filter('posts_join', 'sp_authors_posts_join_filter');

function sp_authors_posts_where_filter($where){
	global $wpdb;
	if(is_author())
		$where = preg_replace('/(\b(?:' . $wpdb->posts . '\.)?post_author\s*=\s*(\d+))/', 
			'($1 OR (' . $wpdb->postmeta . '.meta_value = \'$2\'))', $where, 1);

	return $where;
}
add_filter('posts_where', 'sp_authors_posts_where_filter');
// End: Search of posts

?>