<?php
/*
Plugin Name: Gravity Forms Constant Contact Add-On
Plugin URI: https://gravityforms.com
Description: Integrates Gravity Forms with Constant Contact allowing form submissions to be automatically sent to your Constant Contact account.
Version: 1.7
Author: Gravity Forms
Author URI: https://gravityforms.com
License: GPL-3.0+
Text Domain: gravityformsconstantcontact
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2019-2022 Rocketgenius, Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see http://www.gnu.org/licenses.

*/


// Defines the current version of the Gravity Forms Constant Contact Add-On.
define( 'GF_CONSTANTCONTACT_VERSION', '1.7' );

define( 'GF_CONSTANTCONTACT_MIN_GF_VERSION', '2.2' );

// After GF is loaded, load the add-on.
add_action( 'gform_loaded', array( 'GF_ConstantContact_Bootstrap', 'load_addon' ), 5 );


/**
 * Loads the Gravity Forms Constant Contact Add-On Add-On.
 *
 * Includes the main class and registers it with GFAddOn.
 *
 * @since 1.0
 */
class GF_ConstantContact_Bootstrap {

	/**
	 * Loads the required files.
	 *
	 * @since 1.0
	 * @access public
	 * @static
	 */
	public static function load_addon() {

		// Requires the class file.
		require_once plugin_dir_path( __FILE__ ) . 'class-gf-constantcontact.php';

		// Registers the class name with GFAddOn.
		GFAddOn::register( 'GF_ConstantContact' );
	}
}

/**
 * Returns an instance of the GF_ConstantContact class
 *
 * @since 1.0
 * @return GF_ConstantContact An instance of the GF_ConstantContact class
 */
function gf_constantcontact() {
	return GF_ConstantContact::get_instance();
}
