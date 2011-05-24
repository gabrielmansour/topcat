<?php
/*
Plugin Name: Top Cat
Plugin URI: https://github.com/gabrielmansour/topcat
Old Plugin URI: http://www.thunderguy.com/semicolon/wordpress/top-cat-wordpress-plugin/
Description: Specify a primary category for posts. Also compatible with other taxonomies / post types.
Version: 2.0
Author: Gabriel Mansour, based on the plugin by Bennett McElwee
Author URI: http://www.thunderguy.com/semicolon/

Copyright (C) 2005-07 Bennett McElwee (bennett at thunderguy dotcom)
Copyright (C) 2011 Gabriel Manosur (gabriel at gabrielmansour dotcom)

This program is free software; you can redistribute it and/or
modify it under the terms of version 2 of the GNU General Public
License as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details, available at
http://www.gnu.org/copyleft/gpl.html
or by writing to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

/*
DEVELOPMENT NOTES

All template tags begin with "topcat_"
All internal globals begin with "tguy_tc_" (for Thunderguy Top Cat)
Tested with PHP 5.2.13, WordPress 3.1.x

N.B.: While this plugin now works with Custom Taxonomies, it currently only 
supports one prioritized taxonomy at a time.

TO DO

- Add a new template tage like "the_category" that automatically links.
- When user saves post, aAUtomatically add the main category to the category list.
- When a new category is added with ajax, add a new radio button too.
*/

/*	==================================================
	Template functions
	Displaying the main category of posts.
	All functions can be called in The Loop with no arguments, or anywhere
	with a post ID as argument.
*/

function topcat_the_main_category($post_id = 0, $before = '', $after = '') {
/*	Write the name of the post's main category if there is one, otherwise nothing.
	If there is a main category, also write the before and after strings.
*/
	$cat = topcat_get_the_main_category($post_id);
	if ('' != $cat) {
		echo $before . $cat . $after;
	}
}

function topcat_the_main_category_slug($post_id = 0) {
/*	Write the slug of the post's main category if there is one, otherwise nothing.
*/
	$cat = topcat_get_the_main_category_slug($post_id);
	if ('' != $cat) {
		echo $cat;
	}
}

function topcat_the_main_category_id($post_id = 0) {
/*	Write the ID of the post's main category if there is one, otherwise nothing.
*/
	$cat_id = topcat_get_the_main_category_id($post_id);
	if (0 < $cat_id) {
		echo $cat_id;
	}
}


function topcat_get_the_main_category($post_id = 0) {
/*	Return the name of post's main category.
*/
	return get_the_category_by_ID(topcat_get_the_main_category_id($post_id));
}

function topcat_get_the_main_category_slug($post_id = 0) {
/*	Return the slug name of the post's main category.
*/
  global $TOPCAT_TAXONOMY;
	$cat_id = topcat_get_the_main_category_id($post_id);
	//if (!$cat_id) return null; // safety
	$category = &get_term($cat_id, $TOPCAT_TAXONOMY);
	return $category->slug;
}

/**	Return ID of the post's main category.
 */
function topcat_get_the_main_category_id($post_id = 0) {
	global $post, $TOPCAT_TAXONOMY;
	if ($post_id == 0) $post_id = $post->ID; 
	$category_id = intval( get_post_meta($post_id, "main_$taxonomy", true) );
	return $category_id;
}



/*	==================================================
	Machinery to set and display the main category of posts in the
	admin interface.
*/
add_action('init', 'add_main_category_support');
function add_main_category_support(){
  add_main_term_support('category');
}

function add_main_term_support($taxonomy) {
  global $TOPCAT_TAXONOMY;
  
  if (taxonomy_exists($taxonomy)) {
    $TOPCAT_TAXONOMY = $taxonomy;
  } else {
    return new WP_Error('nonexistent-taxonomy', "A taxonomy named '$taxonomy' doesn't exist.", $taxonomy);
  }

  // Add the radio buttons to the edit form (using DOM). This goes in the
  // footer but might be better off in the edit form actions.
  add_filter('admin_footer', 'tguy_tc_add_radios');

  // Add the old main category to the edit form
  add_action('quick_edit_custom_box',   'tguy_tc_add_hidden_field');
  add_action('bulk_edit_custom_box',   'tguy_tc_add_hidden_field');

  add_action('edit_form_advanced', 'tguy_tc_add_hidden_field');
  add_action('edit_page_form', 'tguy_tc_add_hidden_field'); // for Pages
  

  // Save the main category when saving a new post or editing an old one
  add_action('save_post', 'tguy_tc_save_main_category');
  add_action('edit_post', 'tguy_tc_save_main_category');
}


function tguy_tc_add_radios() {
/*	If the current page is post.php, add radio buttons for all
	categories in addition to the checkboxes. Select one of the
	radio buttons based on the value of the 'old_main_category'
	hidden form field.
*/
	if (strpos($_SERVER['REQUEST_URI'], 'post.php')
	||  strpos($_SERVER['REQUEST_URI'], 'post-new.php')) { // post-new for WP 2.1
	// For details on the createNamedElement() function, see
	// http://www.thunderguy.com/semicolon/2005/05/23/setting-the-name-attribute-in-internet-explorer/
	global $TOPCAT_TAXONOMY, $post_ID;
?>
<script type="text/javascript"><!--
jQuery(function($){

// Get all the checkboxes into a separate array now, since the getElementsByTagName()
// return value will be mutated when we start adding the radio buttons.
$('input:checkbox', '#<?php echo esc_js($TOPCAT_TAXONOMY) ?>div').each(function(){
  // Make sure it really is a category checkbox
  if (this.id == "in-<?php echo esc_js($TOPCAT_TAXONOMY) ?>-"+this.value) {
    // Add a "main category" radio button before the given checkbox
    // (which sits inside a label). Return true on success, else false.
    var catCheckbox = this,
        cat = catCheckbox.value,
  	    catLabel = catCheckbox.parentNode,
  	    $button = $('<input>', {
      	  'type': 'radio',
      	  'name': "main_<?php echo esc_js($TOPCAT_TAXONOMY) ?>",
      	  'val': cat, 
      	  'id': "main_<?php echo esc_js($TOPCAT_TAXONOMY) ?>-"+cat,
      	  'click': function(e){
      	    $(catCheckbox).attr('checked', true);
      	  },
      	  'title': 'Set ' + $(catCheckbox).text() + ' as the main <?php echo esc_js($TOPCAT_TAXONOMY) ?>'
      	}).css('display', 'inline').insertBefore(catLabel);
  }
});

// Select the main category's radio button:
$("#old_main_<?php echo esc_js($TOPCAT_TAXONOMY) ?>").each(function(){
	$("input[type=radio]#main_<?php echo esc_js($TOPCAT_TAXONOMY) ?>-"+this.value, '#<?php echo esc_js($TOPCAT_TAXONOMY) ?>div')
	  .attr('checked', true);
});


});
//--></script>
<?php
	}
}

/*	Add a hidden field with id="old_main_$TAXONOMY" and value read from
	the database to the current page.
*/
function tguy_tc_add_hidden_field() {
	global $post_ID, $TOPCAT_TAXONOMY;
	$topCat = intval( get_post_meta($post_ID, "main_$TOPCAT_TAXONOMY", true) );
	echo '<input type="hidden" name="old_main_'.$TOPCAT_TAXONOMY.'" id="old_main_'.$TOPCAT_TAXONOMY.'" value="'.$topCat.'" />';
}

/*	Save the POSTed value of the main category to the given post ID.
*/
function tguy_tc_save_main_category($post_ID) {
  global $TOPCAT_TAXONOMY;
	$main_term = 0;
	if (isset($_POST["main_$TOPCAT_TAXONOMY"])) {
		$main_term = (int) $_POST["main_$TOPCAT_TAXONOMY"];
	} else {
		// If there's only one category, then make it the main one
		$terms = get_the_terms($post_ID, $TOPCAT_TAXONOMY);
		if ( !is_wp_error($terms) && (1 == count($terms)) ) {
			$main_term = (int) $terms[0]->term_id;
		}
	}
	if (0 < $main_term) {
		update_post_meta($post_ID, "main_$TOPCAT_TAXONOMY", $main_term);
	}
}

?>