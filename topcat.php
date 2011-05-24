<?php
/*
Plugin Name: Top Cat
Plugin URI: http://www.thunderguy.com/semicolon/wordpress/top-cat-wordpress-plugin/
Description: Specify a principal category for posts.
Version: 1.4
Author: Bennett McElwee
Author URI: http://www.thunderguy.com/semicolon/

$Revision$

Copyright (C) 2005-07 Bennett McElwee (bennett at thunderguy dotcom)

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
Tested with PHP 4.3.8, WordPress 1.5.x, 2.0.x, 2.1 beta 

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
	Fi there is a main category, also write the before and after strings.
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
	$cat_id = topcat_get_the_main_category_id($post_id);
	$category = &get_category($cat_id);
	return $category->category_nicename;
}

function topcat_get_the_main_category_id($post_id = 0) {
/*	Return ID of the post's main category.
*/
	global $post, $wpdb;
	if (0 != $post_id) {
		$category_id = $wpdb->get_var("SELECT post_category FROM $wpdb->posts WHERE ID = $post_id");
	} else {
		$category_id = $post->post_category;
	}
	return $category_id;
}



/*	==================================================
	Machinery to set and display the main category of posts in the
	admin interface.
*/

// Add the radio buttons to the edit form (using DOM). This goes in the
// footer but might be better off in the edit form actions.
add_filter('admin_footer', 'tguy_tc_add_radios');

// Add the old main category to the edit form
add_action('simple_edit_form',   'tguy_tc_add_hidden_field');
add_action('edit_form_advanced', 'tguy_tc_add_hidden_field');

// Save the main category when saving a new post or editing an old one
add_action('save_post', 'tguy_tc_save_main_category');
add_action('edit_post', 'tguy_tc_save_main_category');

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
?>
<script language="JavaScript" type="text/javascript"><!--

function createNamedElement(type, name) {
   var element = null;
   // Try the IE way; this fails on standards-compliant browsers
   try {
      element = document.createElement('<'+type+' name="'+name+'">');
   } catch (e) {
   }
   if (!element || element.nodeName != type.toUpperCase()) {
      // Non-IE browser; use canonical method to create named element
      element = document.createElement(type);
      element.name = name;
   }
   return element;
}

function addRadio(catBox) {
// Add a "main category" radio button before the given checkbox
// (which sits inside a label). Return true on success, else false.
	var cat = catBox.value;
	var catLabel = catBox.parentNode;
	var theButton = createNamedElement('input', 'main_category');
	if (!theButton) {
		return false;
	}
	theButton.type = 'radio';
	theButton.value = cat;
	theButton.id = "main_category-"+cat;
	//theButton.onclick = if radio is now checked, check the corresponding checkbox
	//theButton.title = help text? does this work?

	// insert radio button before label
	catLabel.style.display = 'inline';
	catLabel.parentNode.insertBefore(theButton, catLabel);

	return true;
}

// Get all the checkboxes into a separate array now, since the getElementsByTagName()
// return value will be mutated when we start adding the radio buttons.
var catBoxes = new Array();
var categoryDiv = document.getElementById("categorydiv");
if (categoryDiv) {
	var inputs = categoryDiv.getElementsByTagName("input");
	for (var i = 0; i < inputs.length; ++i) {
		var input = inputs[i];
		// Make sure it really is a category checkbox
		if (input.type == "checkbox"
		&& (   input.id ==    "category-"+input.value // up to WordPress 2.0.5
			|| input.id == "in-category-"+input.value)) { // WordPress 2.1+
			catBoxes[catBoxes.length] = input;
		}
	}
}

// Add a radio button before each checkbox
for (var i = 0; i < catBoxes.length; ++i) {
	addRadio(catBoxes[i]);
}

// Select the main category's radio button
var topCatElement = document.getElementById("old_main_category");
if (topCatElement) {
	var topCat = topCatElement.value;
	var topCatRadio = document.getElementById("main_category-"+topCat);
	if (topCatRadio) {
		topCatRadio.checked = true;
	}
}

//--></script>
<?php
	}
}

/*	Add a hidden field with id="old_main_category" and value read from
	the database to the current page.
*/
function tguy_tc_add_hidden_field() {
	global $wpdb, $post_ID;
	$topCat = $wpdb->get_var("SELECT post_category FROM $wpdb->posts WHERE ID='$post_ID'");
	echo '<input type="hidden" name="old_main_category" id="old_main_category" value="'.$topCat.'" />';
}

/*	Save the POSTed value of the main category to the given post ID.
*/
function tguy_tc_save_main_category($post_ID) {
	global $wpdb;
	$main_category = 0;
	if (isset($_POST['main_category'])) {
		$main_category = (int) $_POST['main_category'];
	} else {
		// If there's only one category, then make it the main one
		$catArray = get_the_category($post_ID);
		if (1 == count($catArray)) {
			$main_category = (int) $catArray[0]->cat_ID;
		}
	}
	if (0 < $main_category) {
		$wpdb->query("UPDATE $wpdb->posts SET post_category = '" . $main_category . "' WHERE ID = '$post_ID'");
	}
}

?>