<?php

// Include the Gravity Forms Add-On Framework
GFForms::include_feed_addon_framework();

/**
 * Gravity Forms Constant Contact Add-On.
 *
 * The class name should be GF_Constant_Contact but unfortunately it conflicts with the legacy version.
 * So we use GF_ConstantContact instead.
 *
 * @since     1.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2019, Rocketgenius
 */
class GF_ConstantContact extends GFFeedAddOn {
	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since 1.0
	 * @access private
	 * @var GF_ConstantContact $_instance If available, contains an instance of this class
	 */
	private static $_instance = null;

	/**
	 * Version of this add-on which requires reauthentication with the API.
	 *
	 * Anytime updates are made to this class that requires a site to reauthenticate Gravity Forms with Constant Contact, this
	 * constant should be updated to the value of GF_ConstantContact::$version.
	 *
	 * @since 1.6
	 *
	 * @see GF_ConstantContact::$version
	 */
	const LAST_REAUTHENTICATION_VERSION = '1.5.1';

	/**
	 * Defines the version of the Gravity Forms Constant Contact Add-On Add-On.
	 *
	 * @since 1.0
	 * @access protected
	 * @var string $_version Contains the version.
	 */
	protected $_version = GF_CONSTANTCONTACT_VERSION;
	/**
	 * Defines the minimum Gravity Forms version required.
	 *
	 * @since 1.0
	 * @access protected
	 * @var string $_min_gravityforms_version The minimum version required.
	 */
	protected $_min_gravityforms_version = GF_CONSTANTCONTACT_MIN_GF_VERSION;
	/**
	 * Defines the plugin slug.
	 *
	 * @since 1.0
	 * @access protected
	 * @var string $_slug The slug used for this plugin.
	 */
	protected $_slug = 'gravityformsconstantcontact';
	/**
	 * Defines the main plugin file.
	 *
	 * @since 1.0
	 * @access protected
	 * @var string $_path The path to the main plugin file, relative to the plugins folder.
	 */
	protected $_path = 'gravityformsconstantcontact/constantcontact.php';
	/**
	 * Defines the full path to this class file.
	 *
	 * @since 1.0
	 * @access protected
	 * @var string $_full_path The full path.
	 */
	protected $_full_path = __FILE__;
	/**
	 * Defines the URL where this add-on can be found.
	 *
	 * @since 1.0
	 * @access protected
	 * @var string
	 */
	protected $_url = 'https://gravityforms.com';
	/**
	 * Defines the title of this add-on.
	 *
	 * @since 1.0
	 * @access protected
	 * @var string $_title The title of the add-on.
	 */
	protected $_title = 'Gravity Forms Constant Contact Add-On';
	/**
	 * Defines the short title of the add-on.
	 *
	 * @since 1.0
	 * @access protected
	 * @var string $_short_title The short title.
	 */
	protected $_short_title = 'Constant Contact';

	/**
	 * Defines if Add-On should use Gravity Forms servers for update data.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    bool
	 */
	protected $_enable_rg_autoupgrade = true;

	/**
	 * Defines the capabilities needed for the Constant Contact Add-On
	 *
	 * @since  1.0
	 * @access protected
	 * @var    array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array( 'gravityforms_constantcontact', 'gravityforms_constantcontact_uninstall' );

	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gravityforms_constantcontact';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms_constantcontact';

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gravityforms_constantcontact_uninstall';

	/**
	 * Contains an instance of the Constant Contact API library, if available.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    GF_ConstantContact_API $api If available, contains an instance of the Constant Contact API library.
	 */
	protected $api = null;

	/**
	 * Enabling background feed processing to prevent API performance and token refresh issues delaying form submission completion.
	 *
	 * @since 1.7
	 *
	 * @var bool
	 */
	protected $_async_feed_processing = true;

	/**
	 * Defines how many times the add-on should retry to refresh the token before disconnecting.
	 *
	 * @since 1.7
	 */
	const CC_REFRESH_RETRIES_BEFORE_DISCONNECT = 6;

	/**
	 * Returns an instance of this class, and stores it in the $_instance property.
	 *
	 * @since 1.0
	 * @access public
	 * @return GF_ConstantContact $_instance An instance of the GF_ConstantContact class
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GF_ConstantContact();
		}

		return self::$_instance;
	}

	private function __clone() {
	} /* do nothing */

	/**
	 * Register needed plugin hooks and PayPal delayed payment support.
	 *
	 * @access public
	 * @return void
	 */
	public function init() {

		parent::init();

		add_filter( 'gform_settings_header_buttons', array( $this, 'filter_gform_settings_header_buttons' ), 99 );

		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__( 'Subscribe contact to Constant Contact only when payment is received.', 'gravityformsconstantcontact' ),
			)
		);
	}

	/**
	 * Add AJAX callbacks.
	 *
	 * @since  1.0
	 */
	public function init_ajax() {
		parent::init_ajax();

		// Add AJAX callback for de-authorizing with Constant Contact.
		add_action( 'wp_ajax_gfconstantcontact_deauthorize', array( $this, 'ajax_deauthorize' ) );

		// Add AJAX callback to get auth url.
		add_action( 'wp_ajax_gfconstantcontact_get_auth_url', array( $this, 'ajax_get_auth_url' ) );
	}

	/**
	 * Admin initial actions.
	 *
	 * @since 1.0
	 */
	public function init_admin() {
		parent::init_admin();

		add_action( 'admin_init', array( $this, 'request_access_token' ) );
		add_action( 'admin_notices', array( $this, 'display_notices' ) );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since  1.0
	 *
	 * @return array
	 */
	public function scripts() {

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		$scripts = array(
			array(
				'handle'  => 'gform_constantcontact_pluginsettings',
				'deps'    => array( 'jquery' ),
				'src'     => $this->get_base_url() . "/js/plugin_settings{$min}.js",
				'version' => $this->_version,
				'enqueue' => array(
					array(
						'admin_page' => array( 'plugin_settings' ),
						'tab'        => $this->_slug,
					),
				),
				'strings' => array(
					'auth'         => esc_html__( "Do you use your Constant Contact account ONLY for this website?\n\nIf not, please set up a custom app.", 'gravityformsconstantcontact' ),
					'disconnect'   => esc_html__( 'Are you sure you want to disconnect from Constant Contact?', 'gravityformsconstantcontact' ),
					'settings_url' => admin_url( 'admin.php?page=gf_settings&subview=' . $this->get_slug() ),
					'ajax_nonce'   => wp_create_nonce( 'gfconstantcontact_ajax' ),
				),
			),
		);

		return array_merge( parent::scripts(), $scripts );

	}

	/**
	 * Enqueue needed stylesheets.
	 *
	 * @since  1.0
	 *
	 * @return array
	 */
	public function styles() {

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		$styles = array(
			array(
				'handle'  => 'gform_constantcontact_pluginsettings',
				'src'     => $this->get_base_url() . "/css/plugin_settings{$min}.css",
				'version' => $this->_version,
				'enqueue' => array(
					array(
						'admin_page' => array( 'plugin_settings' ),
						'tab'        => $this->_slug,
					),
				),
			),
		);

		return array_merge( parent::styles(), $styles );

	}

	/**
	 * Return the plugin's icon for the plugin/form settings menu.
	 *
	 * @since 1.4
	 *
	 * @return string
	 */
	public function get_menu_icon() {

		return file_get_contents( $this->get_base_path() . '/images/menu-icon.svg' );

	}

	/**
	 * Maybe save access token.
	 *
	 * @since 1.0
	 * @since 1.4 Added CSRF nonce (the state param).
	 *
	 * @return void
	 */
	public function plugin_settings_page() {
		// Check the state value.
		if ( rgget( 'state' ) && ! wp_verify_nonce( rgget( 'state' ), $this->get_authentication_state_action() ) ) {
			GFCommon::add_error_message( esc_html__( 'Unable to connect to Constant Contact due to mismatched state.', 'gravityformsconstantcontact' ) );
		}

		$auth_payload = rgget( 'auth_payload' );

		if ( $auth_payload == 'false' ) {
			// Add error message.
			GFCommon::add_error_message( esc_html__( 'Unable to connect to Constant Contact.', 'gravityformsconstantcontact' ) );
		}

		return parent::plugin_settings_page();
	}

	/**
	 * Setup plugin settings fields.
	 *
	 * @since  1.0
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {

		return array(
			array(
				'description' => sprintf(
					'<p>%s</p>',
					sprintf(
						esc_html__( 'Constant Contact makes it easy to send email newsletters to your customers, manage your subscriber lists, and track campaign performance. Use Gravity Forms to collect customer information and automatically add it to your Constant Contact subscriber list. If you don\'t have a Constant Contact account, you can %1$s sign up for one here.%2$s', 'gravityformsconstantcontact' ),
						'<a href="https://www.constantcontact.com/?pn=gravityforms" target="_blank">', '</a>' . $this->get_instructions()
					)
				),
				'fields'      => array(
					array(
						'name'              => 'auth_token',
						'type'              => 'auth_token',
						'feedback_callback' => array( $this, 'initialize_api' ),
					),
				),
			),
		);

	}

	/**
	 * Hide submit button on plugin settings page.
	 *
	 * @since 1.4
	 *
	 * @param string $html
	 *
	 * @return string
	 */
	public function filter_gform_settings_header_buttons( $html = '' ) {

		// If this is not the plugin settings page, return.
		if ( ! $this->is_plugin_settings( $this->get_slug() ) ) {
			return $html;
		}

		return '';

	}

	/**
	 * Prepare settings to be rendered on feed settings tab.
	 *
	 * @since 1.0
	 *
	 * @return array $fields - The feed settings fields
	 */
	public function feed_settings_fields() {
		$settings = array(
			array(
				'title'  => '',
				'fields' => array(
					array(
						'name'     => 'feedName',
						'label'    => __( 'Name', 'gravityformsconstantcontact' ),
						'type'     => 'text',
						'class'    => 'medium',
						'required' => true,
						'tooltip'  => '<h6>' . __( 'Name', 'gravityformsconstantcontact' ) . '</h6>' . __( 'Enter a feed name to uniquely identify this setup.', 'gravityformsconstantcontact' ),
					),
					array(
						'name'     => 'list',
						'label'    => esc_html__( 'Constant Contact List', 'gravityformsconstantcontact' ),
						'type'     => 'constantcontact_list',
						'required' => true,
						'tooltip'  => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Constant Contact List', 'gravityformsconstantcontact' ),
							esc_html__( 'Select the Constant Contact list you would like to add your contacts to.', 'gravityformsconstantcontact' )
						),
					),
				),
			),
			array(
				'dependency' => 'list',
				'fields'     => array(
					array(
						'name'      => 'fields',
						'label'     => __( 'Map Fields', 'gravityformsconstantcontact' ),
						'type'      => 'field_map',
						'field_map' => $this->fields_for_feed_mapping(),
						'tooltip'   => '<h6>' . __( 'Map Fields', 'gravityformsconstantcontact' ) . '</h6>' . __( 'Select which Gravity Form fields pair with their respective Constant Contact fields.', 'gravityformsconstantcontact' ),
					),
					array(
						'name'    => 'feed_condition',
						'label'   => __( 'Conditional Logic', 'gravityformsconstantcontact' ),
						'type'    => 'feed_condition',
						'tooltip' => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Conditional Logic', 'gravityformsconstantcontact' ),
							esc_html__( 'When conditional logic is enabled, form submissions will only be exported to Constant Contact when the conditions are met. When disabled all form submissions will be exported.', 'gravityformsconstantcontact' )
						),

					),
				),
			),
		);

		if ( $this->initialize_api() ) {
			$custom_fields = $this->api->get_custom_fields();

			if ( is_wp_error( $custom_fields ) ) {
				$this->log_debug( __METHOD__ . '(): No custom fields were set.' );
			} elseif ( count( $custom_fields['custom_fields'] ) > 0 ) {
				$cf_field = array(
					array(
						'name'                => 'custom_fields',
						'label'               => __( 'Custom Fields', 'gravityformsconstantcontact' ),
						'type'                => 'dynamic_field_map',
						'disable_custom'      => true,
						'limit'               => 25,
						'exclude_field_types' => 'creditcard',
						'tooltip'             => '<h6>' . __( 'Custom Fields', 'gravityformsconstantcontact' ) . '</h6>' . __( 'Select custom fields in Constant Contact to pair with Gravity Forms fields.', 'gravityformsconstantcontact' ),
						'field_map'           => $this->custom_fields_for_feed_mapping( $custom_fields['custom_fields'] ),
					),
				);
				$settings = $this->add_field_after( 'fields', $cf_field, $settings );
			}
		}

		return $settings;
	}

	/**
	 * Add custom fields for mapping.
	 *
	 * @since 1.0
	 *
	 * @param array $custom_fields Custom fields.
	 *
	 * @return array
	 */
	private function custom_fields_for_feed_mapping( $custom_fields ) {
		$field_map = array(
			array(
				'name'  => esc_html__( 'Select a Custom Field', 'gravityformsconstantcontact' ),
				'value' => '',
				'label' => esc_html__( 'Select a Custom Field', 'gravityformsconstantcontact' ),
			),
		);

		foreach ( $custom_fields as $custom_field ) {
			$field       = array(
				'name'  => $custom_field['custom_field_id'],
				'value' => $custom_field['custom_field_id'],
				'label' => $custom_field['label'],
			);
			$field_map[] = $field;
		}

		return $field_map;
	}

	/**
	 * Prepare fields for field mapping feed settings field.
	 *
	 * @since 1.0
	 *
	 * @return array $field_map
	 */
	public function fields_for_feed_mapping() {

		/* Setup initial field map */
		$field_map = array(
			array(
				'name'       => 'email_address',
				'label'      => __( 'Email Address', 'gravityformsconstantcontact' ),
				'required'   => true,
				'field_type' => array( 'email' ),
			),
			array(
				'name'       => 'first_name',
				'label'      => __( 'First Name', 'gravityformsconstantcontact' ),
				'field_type' => array( 'name', 'text' ),
			),
			array(
				'name'       => 'last_name',
				'label'      => __( 'Last Name', 'gravityformsconstantcontact' ),
				'required'   => false,
				'field_type' => array( 'name', 'text' ),
			),
			array(
				'name'     => 'job_title',
				'label'    => __( 'Job Title', 'gravityformsconstantcontact' ),
				'required' => false,
			),
			array(
				'name'     => 'company_name',
				'label'    => __( 'Company Name', 'gravityformsconstantcontact' ),
				'required' => false,
			),
			array(
				'name'       => 'home_number',
				'label'      => __( 'Home Phone Number', 'gravityformsconstantcontact' ),
				'required'   => false,
				'field_type' => array( 'phone', 'text' ),
			),
			array(
				'name'       => 'mobile_number',
				'label'      => __( 'Mobile Phone Number', 'gravityformsconstantcontact' ),
				'required'   => false,
				'field_type' => array( 'phone', 'text' ),
			),
			array(
				'name'       => 'work_number',
				'label'      => __( 'Work Phone Number', 'gravityformsconstantcontact' ),
				'required'   => false,
				'field_type' => array( 'phone', 'text' ),
			),
			array(
				'name'       => 'address_line_1',
				'label'      => __( 'Address', 'gravityformsconstantcontact' ),
				'required'   => false,
				'field_type' => array( 'address', 'text' ),
			),
			array(
				'name'       => 'city_name',
				'label'      => __( 'City', 'gravityformsconstantcontact' ),
				'required'   => false,
				'field_type' => array( 'address', 'text' ),
			),
			array(
				'name'       => 'state_name',
				'label'      => __( 'State', 'gravityformsconstantcontact' ),
				'required'   => false,
				'field_type' => array( 'address', 'text' ),
			),
			array(
				'name'       => 'zip_code',
				'label'      => __( 'ZIP Code', 'gravityformsconstantcontact' ),
				'required'   => false,
				'field_type' => array( 'address', 'text' ),
			),
			array(
				'name'       => 'country_name',
				'label'      => __( 'Country', 'gravityformsconstantcontact' ),
				'required'   => false,
				'field_type' => array( 'address', 'text' ),
			),
		);

		return $field_map;

	}

	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function feed_list_columns() {
		return array(
			'feedName' => __( 'Name', 'gravityformsconstantcontact' ),
			'list'     => __( 'Constant Contact List', 'gravityformsconstantcontact' ),
		);
	}

	/**
	 * Set feed creation control.
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	public function can_create_feed() {
		return (bool) $this->initialize_api();
	}

	/**
	 * Enable feed duplication.
	 *
	 * @since 1.0
	 *
	 * @param int $feed_id Feed ID.
	 *
	 * @return bool
	 */
	public function can_duplicate_feed( $feed_id ) {
		return true;
	}

	/**
	 * Returns the value to be displayed in the list name column.
	 *
	 * @since 1.0
	 *
	 * @param array $feed The feed being included in the feed list.
	 *
	 * @return string
	 */
	public function get_column_value_list( $feed ) {
		/* If Constant Contact instance is not initialized, return campaign ID. */
		if ( ! $this->initialize_api() ) {
			return rgars( $feed, 'meta/list' );
		}

		// Get list.
		$list = $this->api->get_list( rgars( $feed, 'meta/list' ) );

		if ( is_wp_error( $list ) ) {
			// Log error.
			$this->log_debug( __METHOD__ . '(): Unable to get Constant Contact list for feed list; ' . print_r( $list->get_error_messages(), true ) );

			// Return list ID.
			return rgars( $feed, 'meta/list' );
		}

		// Return list name.
		return rgar( $list, 'name' );
	}

	/**
	 * Create Generate Auth Token settings field.
	 *
	 * @since  1.0
	 *
	 * @param array $field Field settings.
	 * @param bool  $echo Display field. Defaults to true.
	 *
	 * @return string
	 */
	public function settings_auth_token( $field, $echo = true ) {

		// Initialize return HTML.
		$html = '';

		// If Constant Contact is authenticated, display de-authorize button.
		if ( $this->initialize_api() ) {
			$html .= '<p>' . esc_html__( 'Connected to Constant Contact.', 'gravityformsconstantcontact' );
			$html .= '</p>';
			$html .= sprintf(
				' <a href="#" class="button" id="gform_constantcontact_deauth_button">%1$s</a>',
				esc_html__( 'Disconnect from Constant Contact', 'gravityformsconstantcontact' )
			);
		} else {
			// If SSL is available, display custom app settings.
			if ( is_ssl() ) {

				$html .= $this->custom_app_settings();

			} else {

				$headline  = esc_html__( 'SSL Certificate Required', 'gravityformsconstantcontact' ) . '</h4>';
				$paragraph = sprintf( esc_html__( 'Make sure you have an SSL certificate installed and enabled, then %1$sclick here to continue%2$s.', 'gravityformsconstantcontact' ), '<a href="' . admin_url( 'admin.php?page=gf_settings&subview=gravityformsconstantcontact', 'https' ) . '">', '</a>' );

				if ( version_compare( GFForms::$version, '2.5-dev-1', '<' ) ) {
					$html .= sprintf( '<div class="alert_red" style="padding:20px; padding-top:5px;"><h4>%s</h4>%s</div>', $headline, $paragraph );
				} else {
					$html .= sprintf( '<div class="alert gforms_note_error">%s<br />%s</div>', $headline, $paragraph );
				}

			}
		}

		if ( $echo ) {
			echo $html;
		}

		return $html;

	}

	/**
	 * Renders instructions for creating Constant Contact app.
	 *
	 * @since  1.0
	 *
	 * @return string
	 */
	public function get_instructions() {

		// Don't display instructions if page is not under SSL or if already connected to Constant Contact.
		if ( ! is_ssl() || $this->initialize_api() ) {
			return '';
		}

		$html  = '<h4>' . esc_html__( 'An application must be created with Constant Contact to get your API Key and App Secret. The app should be dedicated to this website, please do not use the same app with multiple sites.', 'gravityformsconstantcontact' ) . '</h4>';
		$html .= '<ol>';
		$html .= '<li>' . sprintf( esc_html__( 'Login to the %1$sConstant Contact V3 Portal%2$s and create a "New Application".', 'gravityformsconstantcontact' ), '<a href="https://app.constantcontact.com/pages/dma/portal" target="_blank">', '</a>' ) . '</li>';
		$html .= '<li>' . esc_html__( 'Enter "Gravity Forms" for the application name and click "Save".', 'gravityformsconstantcontact' ) . '</li>';
		$html .= '<li>' . esc_html__( 'Copy your Constant Contact API Key and paste it into the API Key field below.', 'gravityformsconstantcontact' ) . '</li>';
		$html .= '<li>' . esc_html__( 'Click the "Generate Secret" button, go through the secret generation process and paste the resulted key in the "App Secret" field below.', 'gravityformsconstantcontact' ) . '</li>';
		$html .= '<li>' . sprintf( esc_html__( 'Paste the URL %s into the Redirect URI field and click "Save" in the top right corner of the screen.', 'gravityformsconstantcontact' ), '<strong>' . $this->get_redirect_uri() . '</strong>' ) . '</li>';
		$html .= '</ol>';

		return $html;
	}
	/**
	 * Renders settings section for custom Constant Contact app.
	 *
	 * @since  1.0
	 *
	 * @return string
	 */
	public function custom_app_settings() {

		$html = '';

		// Open custom app table.
		if ( version_compare( GFForms::$version, '2.5-dev-1', '<' ) ) {
			$html .= '<table class="form-table">';
		}

		ob_start();

		// Display custom app key.
		$this->single_setting_row(
			array(
				'name'  => 'custom_app_key',
				'type'  => 'text',
				'label' => esc_html__( 'API Key', 'gravityformsconstantcontact' ),
				'class' => 'medium',
			)
		);

		// Display custom app secret.
		$this->single_setting_row(
			array(
				'name'  => 'custom_app_secret',
				'type'  => 'text',
				'label' => esc_html__( 'App Secret', 'gravityformsconstantcontact' ),
				'class' => 'medium',
			)
		);

		$html .= ob_get_contents();
		ob_end_clean();

		// Display auth button.
		$html .= version_compare( GFForms::$version, '2.5-dev-1', '<' ) ? '<tr><td></td><td>' : '';
		$html .= sprintf(
			'<button type="button" class="primary button large" id="gform_constantcontact_custom_auth_button">%1$s</button>',
			esc_html__( 'Connect to Constant Contact', 'gravityformsconstantcontact' )
		);
		$html .= version_compare( GFForms::$version, '2.5-dev-1', '<' ) ? '</td></tr></table>' : '';

		return $html;

	}

	/**
	 * Get Constant Contact app key.
	 *
	 * @since  1.0
	 *
	 * @return string
	 */
	public function get_app_key() {
		// Get plugin settings.
		$settings = $this->get_plugin_settings();

		return rgar( $settings, 'custom_app_key' ) ? rgar( $settings, 'custom_app_key' ) : null;
	}

	/**
	 * Get Constant Contact app secret.
	 *
	 * @since  1.0
	 *
	 * @return string
	 */
	public function get_app_secret() {

		// Get plugin settings.
		$settings = $this->get_plugin_settings();

		return rgar( $settings, 'custom_app_secret' ) ? rgar( $settings, 'custom_app_secret' ) : null;

	}

	/**
	 * Get OAuth Redirect URI for custom Constant Contact app.
	 *
	 * @since  1.0
	 *
	 * @return string
	 */
	public function get_redirect_uri() {
		return admin_url( 'admin.php', 'https' );
	}

	/**
	 * Get Constant Contact authentication URL.
	 *
	 * @since 1.0
	 * @since 1.4 Used nonce as the state value.
	 *
	 * @param string $app_key Constant Contact app key.
	 *
	 * @return string
	 */
	public function get_auth_url( $app_key = null ) {

		// If app key is empty, get from setting.
		if ( rgblank( $app_key ) ) {
			$app_key = $this->get_app_key();
		}

		// If app key or secret are empty, return null.
		if ( rgblank( $app_key ) ) {
			return null;
		}

		// Get base OAuth URL.
		$auth_url = 'https://authz.constantcontact.com/oauth2/default/v1/authorize';

		// Prepare OAuth URL parameters.
		$auth_params = array(
			'response_type' => 'code',
			'client_id'     => $app_key,
			'scope'         => 'contact_data+offline_access',
			'redirect_uri'  => urlencode( $this->get_redirect_uri() ),
			'state'         => wp_create_nonce( $this->get_authentication_state_action() ),
		);

		// Add parameters to OAuth url.
		$auth_url = add_query_arg( $auth_params, $auth_url );

		return $auth_url;
	}

	/**
	 * Define the markup for the Constant Contact type field.
	 *
	 * @since  1.0
	 *
	 * @param array $field The field properties.
	 * @param bool  $echo Should the setting markup be echoed. Defaults to true.
	 *
	 * @return string
	 */
	public function settings_constantcontact_list( $field, $echo = true ) {
		// Initialize HTML string.
		$html = '';

		// If API is not initialized, return.
		if ( ! $this->initialize_api() ) {
			return $html;
		}

		// Log contact lists request parameters.
		$this->log_debug( __METHOD__ . '(): Retrieving contact lists;' );

		// Get lists.
		$lists = $this->api->get_lists();

		if ( is_wp_error( $lists ) ) {
			// Log that contact lists could not be obtained.
			$this->log_debug( __METHOD__ . '(): Could not retrieve Constant Contact contact lists; ' . print_r( $lists->get_error_messages(), true ) );

			// Display error message.
			printf( esc_html__( 'Could not load Constant Contact contact lists. %sError: %s', 'gravityformconstantcontact' ), '<br/>', $lists->get_error_message() );

			return;
		}

		// If no lists were found, display error message.
		if ( 0 === $lists['lists_count'] ) {
			// Log that no lists were found.
			$this->log_debug( __METHOD__ . '(): Could not load Constant Contact contact lists; no lists found.' );

			// Display error message.
			printf( esc_html__( 'Could not load Constant Contact contact lists. %sError: %s', 'gravityformconstantcontact' ), '<br/>', esc_html__( 'No lists found.', 'gravityformconstantcontact' ) );

			return;
		}

		// Log number of lists retrieved.
		$this->log_debug( __METHOD__ . '(): Number of lists: ' . count( $lists['lists'] ) );

		// Initialize select options.
		$options = array(
			array(
				'label' => esc_html__( 'Select a Constant Contact List', 'gravityformconstantcontact' ),
				'value' => '',
			),
		);

		// Loop through Constant Contact lists.
		foreach ( $lists['lists'] as $list ) {
			// Add list to select options.
			$options[] = array(
				'label' => esc_html( $list['name'] ),
				'value' => esc_attr( $list['list_id'] ),
			);
		}

		// Add select field properties.
		$field['type']     = 'select';
		$field['choices']  = $options;
		$field['onchange'] = 'jQuery(this).parents("form").submit();';

		// Generate select field.
		$html = $this->settings_select( $field, false );

		if ( $echo ) {
			echo $html;
		}

		return $html;

	}

	/**
	 * Initializes Constant Contact API if credentials are valid.
	 *
	 * @since  1.0
	 *
	 * @param array $auth_token Authentication token data.
	 *
	 * @return bool
	 */
	public function initialize_api( $auth_token = null ) {

		// If API is already initialized and auth token is not provided, return true.
		if ( ! is_null( $this->api ) ) {
			return true;
		}

		// Load the API library.
		if ( ! class_exists( 'GF_ConstantContact_API' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'includes/class-gf-constant-contact-api.php';
		}

		// Get the OAuth tokens.
		if ( empty( $auth_token ) ) {
			$auth_token = $this->get_plugin_setting( 'auth_token' );
		}

		// If no OAuth tokens, do not run a validation check.
		if ( empty( $auth_token['access_token'] ) || empty( $auth_token['refresh_token'] ) ) {
			$this->log_debug( __METHOD__ . '(): API tokens are empty.' );

			return false;
		}

		// Log validation step.
		$this->log_debug( __METHOD__ . '(): Validating API Info.' );

		// Setup a new Constant Contact object with the API credentials.
		$ctct = new GF_ConstantContact_API( $auth_token );

		// Assign API library to instance.
		$this->api = $ctct;

		// If the token has an expiry time from Constant Contact, use that. Legacy tokens prior to March 31, 2022 expire 7200 seconds after being used.
		$time_to_expiration = ( ! empty( $auth_token['expires_in'] ) ) ? $auth_token['expires_in'] : 7199;
		if ( time() > $auth_token['date_created'] + $time_to_expiration ) {
			// Log that authentication test failed.
			$this->log_debug( __METHOD__ . '(): API tokens expired, start refreshing.' );

			// refresh token.
			$auth_token = $this->api->refresh_token( $auth_token['refresh_token'] );

			if ( empty( $auth_token['access_token'] ) || empty( $auth_token['refresh_token'] ) ) {
				$this->log_debug( __METHOD__ . '(): Failed refreshing token.' );
				$this->maybe_disconnect();

				if ( ! $this->is_plugin_settings( $this->get_slug() ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/*
	 * Decides if the add-on should disconnect after failed attempts to refresh the token in different requests.
	 *
	 * @since 1.7
	 */
	public function maybe_disconnect() {
		$settings                = $this->get_plugin_settings();
		$failed_refresh_attempts = (int) rgar( $settings, 'failed_refresh_attempts', 3 );

		if ( empty( $settings['first_refresh_failure_timestamp'] ) ) {
			$settings['first_refresh_failure_timestamp'] = time();
			$settings['failed_refresh_attempts']         = $failed_refresh_attempts;
			$this->update_plugin_settings( $settings );

			return;
		}

		$settings['failed_refresh_attempts'] = $failed_refresh_attempts + 3;

		$seconds_since_first_failure = time() - (int) $settings['first_refresh_failure_timestamp'];

		if ( $seconds_since_first_failure < DAY_IN_SECONDS || $failed_refresh_attempts < self::CC_REFRESH_RETRIES_BEFORE_DISCONNECT ) {
			$this->update_plugin_settings( $settings );

			return;
		}

		$this->log_debug( __METHOD__ . sprintf( '(): Disconnecting after %d failed refresh attempts in %d seconds.', $settings['failed_refresh_attempts'], $seconds_since_first_failure ) );
		unset( $settings['auth_token'], $settings['first_refresh_failure_timestamp'], $settings['failed_refresh_attempts'] );
		$this->update_plugin_settings( $settings );
	}

	/**
	 * Processes the feed, subscribes the user to the list.
	 *
	 * @since 1.0
	 *
	 * @param array $feed The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form The form object currently being processed.
	 *
	 * @return array|null Returns a modified entry object or null.
	 */
	public function process_feed( $feed, $entry, $form ) {
		$this->log_debug( __METHOD__ . '(): Processing feed.' );

		/* If Constant Contact instance is not initialized, exit. */
		if ( ! $this->initialize_api() ) {
			$this->add_feed_error( esc_html__( 'Unable to process feed because API could not be initialized.', 'gravityformsconstantcontact' ), $feed, $entry, $form );

			return $entry;
		}

		/* If email address is empty, return. */
		$field_map = $this->get_field_map_fields( $feed, 'fields' );
		$email     = $this->get_field_value( $form, $entry, $field_map['email_address'] );
		if ( GFCommon::is_invalid_or_empty_email( $email ) ) {
			$this->add_feed_error( esc_html__( 'A valid Email address must be provided.', 'gravityformsconstantcontact' ), $feed, $entry, $form );

			return $entry;
		}

		$subscriber_details = $this->build_subscriber_details( $feed, $entry, $form );

		if ( ! $subscriber_details ) {
			$this->add_feed_error( esc_html__( 'Subscriber details invalid.', 'gravityformsconstantcontact' ), $feed, $entry, $form );

			return $entry;
		}

		$subscription_results = $this->subscribe_to_list( $subscriber_details );

		if ( is_wp_error( $subscription_results ) ) {
			$this->add_feed_error( sprintf( esc_html__( 'Unable to add/update subscriber: %s', 'gravityformsconstantcontact' ), $subscription_results->get_error_message() ), $feed, $entry, $form );
		}

		return null;
	}

	/**
	 * Create array of subscriber details using the submitted values
	 *
	 * @sine 1.0
	 *
	 * @param array $feed GF Feed.
	 * @param array $entry GF Entry.
	 * @param array $form GF Form.
	 *
	 * @return array|null Array of subscriber details using CTCT keys. NULL if email isn't valid, email isn't provided, subscriber details are empty.
	 */
	public function build_subscriber_details( $feed, $entry, $form ) {
		$list = rgars( $feed, 'meta/list', false );

		/* Prepare audience member import array. */
		$subscriber_details = array(
			'list_memberships' => array(
				$list,
			),
			'custom_fields'    => array(),
			'phone_numbers'    => array(),
		);

		/* Find all fields mapped and push them to the audience member array. */
		foreach ( $this->get_field_map_fields( $feed, 'fields' ) as $field_name => $field_id ) {
			$field_value = $this->get_field_value( $form, $entry, $field_id );

			if ( ! rgblank( $field_value ) ) {
				$field = GFFormsModel::get_field( $form, $field_id );

				switch ( $field_name ) {
					case 'email_address':
						$field_value = array(
							'address' => $field_value,
						);
						break;
					case 'home_number':
					case 'mobile_number':
					case 'work_number':
						$subscriber_details['phone_numbers'][] = array(
							'phone_number' => $field_value,
							'kind'         => str_replace( '_number', '', $field_name ),
						);

						$field_name = 'phone_numbers';
						break;
				}

				if ( $field && 'address' === $field->type ) {
					$field_name  = 'street_addresses';
					$field_value = trim( $field_value );

					if ( ! isset( $subscriber_details['street_addresses'] ) ) {
						$subscriber_details['street_addresses']    = array();
						$subscriber_details['street_addresses'][0] = array(
							'kind' => 'home',
						);
					}

					if ( $field_id === $field->id . '.1' ) {
						$ctct_field_name = 'street';
					} elseif ( $field_id === $field->id . '.3' ) {
						$ctct_field_name = 'city';
					} elseif ( $field_id === $field->id . '.4' ) {
						$ctct_field_name = 'state';
					} elseif ( $field_id === $field->id . '.5' ) {
						$ctct_field_name = 'postal_code';
					} elseif ( $field_id === $field->id . '.6' ) {
						$ctct_field_name = 'country';
					} elseif ( $field_id === $field->id . '.2' ) {
						$ctct_field_name = 'street';
						$field_value     = $subscriber_details['street_addresses'][0]['street'] . ', ' . $field_value;
					}

					if ( isset( $ctct_field_name ) ) {
						$subscriber_details['street_addresses'][0][ $ctct_field_name ] = $field_value;
					}
				}

				if ( ! isset( $subscriber_details[ $field_name ] ) ) {
					$subscriber_details[ $field_name ] = $field_value;
				}
			}
		}

		/* Push any custom fields to the audience member array. */
		if ( ! empty( $feed['meta']['custom_fields'] ) ) {
			foreach ( $feed['meta']['custom_fields'] as $custom_field ) {
				/* If field map field is not paired to a form field, skip. */
				if ( rgblank( $custom_field['value'] ) ) {
					continue;
				}

				$field_value = $this->get_field_value( $form, $entry, $custom_field['value'] );

				if ( ! rgblank( $field_value ) ) {
					$subscriber_details['custom_fields'][] = array(
						'custom_field_id' => $custom_field['key'],
						'value'           => $field_value,
					);
				}
			}
		}

		$subscriber_details['custom_fields'] = $this->merge_custom_fields( $subscriber_details, $feed, $entry, $form );
		$subscriber_details['phone_numbers'] = $this->merge_phone_numbers( $subscriber_details, $feed, $entry, $form );

		if ( GFCommon::is_empty_array( $subscriber_details ) ) {
			$this->log_debug( __METHOD__ . '(): Empty subscriber details' );

			return null;
		}

		return $subscriber_details;
	}

	/**
	 * Subscribe a contact to lists
	 *
	 * @since 1.0
	 *
	 * @param array $subscriber_details Subscriber details.
	 *
	 * @return true|WP_Error
	 */
	public function subscribe_to_list( $subscriber_details = array() ) {
		foreach ( $subscriber_details as $key => $detail ) {
			if ( is_string( $detail ) ) {
				$detail = trim( $detail );
			}

			if ( empty( $detail ) ) {
				unset( $subscriber_details[ $key ] );
			}
		}

		$contact    = $this->api->contact_exists( $subscriber_details['email_address']['address'] );
		$contact_id = is_wp_error( $contact ) ? false : rgar( $contact, 'contact_id', false );
		$action     = ( $contact_id ) ? 'updated' : 'added';

		// Log the subscriber to be added or updated.
		$this->log_debug( __METHOD__ . "(): Subscriber to be {$action}: " . print_r( $subscriber_details, true ) );

		if ( $action === 'updated' ) {
			$subscriber_details['list_memberships'] = array_merge(
				$subscriber_details['list_memberships'],
				$contact['list_memberships']
			);
		}

		// Add or update subscriber.
		$result = $this->api->update_contact( $subscriber_details, $contact_id );

		if ( is_wp_error( $result ) ) {
			$this->log_debug( __METHOD__ . '(): API errors when attempting subscription: ' . print_r( $result->get_error_messages(), true ) );

			// Add extra notes for certain API errors.
			switch ( $result->get_error_message() ) {
				case 'Conflict':
					$message = esc_html__( 'You\'re trying to add a contact which has been deleted from your Constant Contact account. Currently you have to manually revive the contact via the Constant Contact website.', 'gravityformsconstantcontact' );
					break;
				default:
					$message = $result->get_error_message();
			}

			return new WP_Error( 'constantcontact_api_error', $message );
		}

		// Log that the subscription was added or updated.
		$this->log_debug( __METHOD__ . "(): Subscriber successfully {$action}." );

		return true;
	}

	/**
	 * Constant Contact doesn't have a de-authorize API so we just delete tokens from the Settings.
	 *
	 * @since  1.0
	 */
	public function ajax_deauthorize() {
		// Verify nonce.
		if ( false === wp_verify_nonce( rgget( 'nonce' ), 'gfconstantcontact_ajax' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Access denied.', 'gravityformsconstantcontact' ) ) );
		}

		// If user is not authorized, exit.
		if ( ! GFCommon::current_user_can_any( $this->_capabilities_settings_page ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Access denied.', 'gravityformsconstantcontact' ) ) );
		}

		$settings = $this->get_plugin_settings();

		// Log that we revoked the access token.
		$this->log_debug( __METHOD__ . '(): Access token revoked.' );

		// Remove access token from settings.
		unset( $settings['auth_token'] );

		// Save settings.
		$this->update_plugin_settings( $settings );

		// Return success response.
		wp_send_json_success();
	}

	/**
	 * AJAX handler to get auth url with the app key.
	 *
	 * @since 1.0
	 */
	public function ajax_get_auth_url() {
		// Verify nonce.
		if ( false === wp_verify_nonce( rgget( 'nonce' ), 'gfconstantcontact_ajax' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Access denied.', 'gravityformsconstantcontact' ) ) );
		}

		// If user is not authorized, exit.
		if ( ! GFCommon::current_user_can_any( $this->_capabilities_settings_page ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Access denied.', 'gravityformsconstantcontact' ) ) );
		}

		$settings                      = $this->get_plugin_settings();
		$settings['custom_app_key']    = sanitize_text_field( rgget( 'custom_app_key' ) );
		$settings['custom_app_secret'] = sanitize_text_field( rgget( 'custom_app_secret' ) );

		// Set the API authentication version.
		$settings['reauth_version'] = self::LAST_REAUTHENTICATION_VERSION;

		$this->update_plugin_settings( $settings );

		wp_send_json_success( $this->get_auth_url( $settings['custom_app_key'] ) );
	}

	/**
	 * Request auth tokens from Constant Contact API.
	 *
	 * @since 1.0
	 * @since 1.4 Check if the state matches the value in the settings.
	 *
	 * @return bool|void
	 */
	public function request_access_token() {
		if ( rgblank( rgget( 'code' ) ) || rgget( 'subview' ) || ! wp_verify_nonce( rgget( 'state' ), $this->get_authentication_state_action() ) ) {
			return;
		}

		$settings_url = admin_url( 'admin.php?page=gf_settings&subview=' . $this->get_slug() );

		$tokens = $this->get_tokens();

		if ( ! empty( $tokens['access_token'] ) && ! empty( $tokens ) ) {
			// Get current plugin settings.
			$settings = $this->get_plugin_settings();

			// Add access token to plugin settings.
			$settings['auth_token'] = array(
				'access_token'  => $tokens['access_token'],
				'refresh_token' => $tokens['refresh_token'],
				'date_created'  => time(),
				'expires_in'    => $tokens['expires_in'],
			);

			// Save plugin settings.
			$this->update_plugin_settings( $settings );

			// Redirect to the Constant Contact settings page.
			$redirect_url = add_query_arg(
				array(
					'auth_payload' => 'true',
					'state'        => rgget( 'state' ),
				),
				$settings_url
			);
		} else {
			// Add error flag to redirect URL.
			$redirect_url = add_query_arg(
				array(
					'auth_payload' => 'false',
					'state'        => rgget( 'state' ),
				),
				$settings_url
			);
		}

		wp_safe_redirect( $redirect_url );
		exit();
	}

	/**
	 * Get tokens with authorization code or refresh token.
	 *
	 * @since 1.0
	 *
	 * @param string $refresh_token Refresh token.
	 *
	 * @return array|bool
	 */
	public function get_tokens( $refresh_token = '' ) {
		// Get base OAuth URL.
		$code     = rgget( 'code' );
		$auth_url = ( ! $this->requires_api_reauthentication() ) ? 'https://authz.constantcontact.com/oauth2/default/v1/token' : 'https://idfed.constantcontact.com/as/token.oauth2';

		// Prepare OAuth URL parameters.
		if ( empty( $code ) ) {
			$auth_params = array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $refresh_token,
			);
		} else {
			$auth_params = array(
				'grant_type'   => 'authorization_code',
				'code'         => $code,
				'redirect_uri' => $this->get_redirect_uri(),
			);
		}

		// Add parameters to URL.
		$auth_url = add_query_arg( $auth_params, $auth_url );

		// Execute request.
		$args     = array(
			/**
			 * Sets the HTTP timeout, in seconds, for the request.
			 *
			 * @param int    30        The timeout limit, in seconds. Defaults to 30.
			 * @param string $auth_url The request URL.
			 *
			 * @return int
			 */
			'timeout' => apply_filters( 'http_request_timeout', 30, $auth_url ),
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->get_app_key() . ':' . $this->get_app_secret() ),
			),
		);
		$response = wp_remote_post( $auth_url, $args );

		// If there was an error, return false.
		if ( is_wp_error( $response ) ) {

			$this->log_error( __METHOD__ . '(): API errors when attempting to ' . ( empty( $code ) ? 'refresh' : 'exchange code for' ) . ' token: ' . print_r( $response->get_error_messages(), true ) );

			return false;
		}

		// Get response body.
		$response_body = wp_remote_retrieve_body( $response );

		$this->log_debug( __METHOD__ . sprintf( '(): API response after attempting to %s token: code: %s; body: %s', ( empty( $code ) ? 'refresh' : 'exchange code for' ), wp_remote_retrieve_response_code( $response ), $response_body ) );

		$response_body = json_decode( $response_body, true );

		if ( ! rgar( $response_body, 'access_token' ) ) {
			$this->log_error( __METHOD__ . "(): API response doesn't contain a valid token." );

			return false;
		}

		$this->log_debug( __METHOD__ . '(): Token retrieved successfully' );

		return array(
			'access_token'  => rgar( $response_body, 'access_token' ),
			'refresh_token' => rgar( $response_body, 'refresh_token' ),
			'expires_in'    => rgar( $response_body, 'expires_in' ),
		);
	}

	/**
	 * Return phone numbers from the entry and merge with the current contact data.
	 * If the total phone numbers > 2, we'll add as many new fields and then return the ones failed to add in entry
	 * notes.
	 *
	 * @since 1.0
	 *
	 * @param array $subscriber_details Subscriber details array to be processed.
	 * @param array $feed The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form The form object currently being processed.
	 *
	 * @return array
	 */
	public function merge_phone_numbers( $subscriber_details, $feed, $entry, $form ) {
		$contact = $this->api->get_contact_details( $subscriber_details['email_address']['address'], 'phone_numbers' );

		if ( is_wp_error( $contact ) ) {
			$this->log_debug( __METHOD__ . '(): API errors when attempting subscription: ' . print_r( $contact->get_error_messages(), true ) );
		} elseif ( isset( $contact['phone_numbers'] ) ) {
			$submitted_phone_numbers = $subscriber_details['phone_numbers'];
			if ( count( $contact['phone_numbers'] ) >= 2 ) {
				// CtCt allows only 2 phone numbers can be submitted via API.
				// When there're already more than 2 phone numbers for a contact,
				// we don't update phone numbers at all.
				$subscriber_details['phone_numbers'] = array();
				$unsent_phone_numbers                = $submitted_phone_numbers;
			} elseif ( empty( $submitted_phone_numbers ) ) {
				$subscriber_details['phone_numbers'] = $contact['phone_numbers'];
			} else {
				$subscriber_details['phone_numbers'] = array_merge( $contact['phone_numbers'], $subscriber_details['phone_numbers'] );
				if ( count( $subscriber_details['phone_numbers'] ) > 2 ) {
					$unsent_phone_numbers                = array_slice( $subscriber_details['phone_numbers'], 2 );
					$subscriber_details['phone_numbers'] = array_slice( $subscriber_details['phone_numbers'], 0, 2 );
				}
			}

			if ( isset( $unsent_phone_numbers ) && count( $unsent_phone_numbers ) > 0 ) {
				$unsent_phone_number_numbers = wp_list_pluck( $unsent_phone_numbers, 'phone_number' );

				$this->add_feed_error(
					sprintf( "Some phone numbers couldn't be added, because each contact can only have 2 phone numbers. These phone numbers weren't submitted: %s", implode( ', ', $unsent_phone_number_numbers ) ),
					$feed,
					$entry,
					$form
				);
			}
		}

		return isset( $subscriber_details['phone_numbers'] ) ? $subscriber_details['phone_numbers'] : array();
	}

	/**
	 * Return custom fields from the entry and merge with the current contact data.
	 * If the total custom fields > 25, we'll add as many new fields and then return the ones failed to add in entry
	 * notes.
	 *
	 * @since 1.0
	 *
	 * @param array $subscriber_details Subscriber details array to be processed.
	 * @param array $feed The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form The form object currently being processed.
	 *
	 * @return array
	 */
	public function merge_custom_fields( $subscriber_details, $feed, $entry, $form ) {
		$contact = $this->api->get_contact_details( $subscriber_details['email_address']['address'], 'custom_fields' );

		if ( is_wp_error( $contact ) ) {
			$this->log_debug( __METHOD__ . '(): API errors when attempting subscription: ' . print_r( $contact->get_error_messages(), true ) );
		} elseif ( isset( $contact['custom_fields'] ) ) {
			$submitted_custom_fields = $subscriber_details['custom_fields'];
			if ( count( $contact['custom_fields'] ) >= 25 ) {
				$subscriber_details['custom_fields'] = array();
				$unsent_custom_fields                = $submitted_custom_fields;
			} elseif ( empty( $submitted_custom_fields ) ) {
				$subscriber_details['custom_fields'] = $contact['custom_fields'];
			} else {
				$current_custom_field_ids    = wp_list_pluck( $contact['custom_fields'], 'custom_field_id' );
				$feed_custom_field_ids       = wp_list_pluck( $submitted_custom_fields, 'custom_field_id' );
				$current_custom_field_values = wp_list_pluck( $contact['custom_fields'], 'value' );

				$subscriber_details['custom_fields'] = array();
				foreach ( $current_custom_field_ids as $key => $custom_field_id ) {
					if ( ! in_array( $custom_field_id, $feed_custom_field_ids, true ) ) {
						$subscriber_details['custom_fields'][] = array(
							'custom_field_id' => $custom_field_id,
							'value'           => $current_custom_field_values[ $key ],
						);
					}
				}
				$subscriber_details['custom_fields'] = array_merge( $subscriber_details['custom_fields'], $submitted_custom_fields );

				if ( count( $subscriber_details['custom_fields'] ) > 25 ) {
					$unsent_custom_fields                = array_slice( $subscriber_details['custom_fields'], 25 );
					$subscriber_details['custom_fields'] = array_slice( $subscriber_details['custom_fields'], 0, 25 );
				}
			}

			$custom_fields = $this->api->get_custom_fields();
			$cf_labels     = wp_list_pluck( $custom_fields['custom_fields'], 'label' );
			$cf_field_ids  = wp_list_pluck( $custom_fields['custom_fields'], 'custom_field_id' );

			if ( isset( $unsent_custom_fields ) && count( $unsent_custom_fields ) > 0 ) {
				$unsent_custom_field_labels = array();
				foreach ( $unsent_custom_fields as $unsent_custom_field ) {
					$key = array_search( $unsent_custom_field['custom_field_id'], $cf_field_ids, true );
					if ( false !== $key ) {
						$unsent_custom_field_labels[] = $cf_labels[ $key ];
					}
				}

				$this->add_feed_error(
					sprintf( "Some custom fields cannot be added, because each contact can only has 25 custom fields. The custom fields weren't submitted: %s", implode( ', ', $unsent_custom_field_labels ) ),
					$feed,
					$entry,
					$form
				);
			}
		}

		return isset( $subscriber_details['custom_fields'] ) ? $subscriber_details['custom_fields'] : array();
	}

	/**
	 * Get the authentication state, which was created from a wp nonce.
	 *
	 * @since 1.4
	 *
	 * @return string
	 */
	public function get_authentication_state_action() {
		return 'gform_constantcontact_authentication_state';
	}

	/**
	 * Check whether this add-on needs to be reauthenticated with the Constant Contact API.
	 *
	 * @since 1.6
	 *
	 * @return bool
	 */
	private function requires_api_reauthentication() {
		$settings = $this->get_plugin_settings();

		return ! empty( $settings ) && version_compare( rgar( $settings, 'reauth_version' ), self::LAST_REAUTHENTICATION_VERSION, '<' );
	}

	/**
	 * Display a notification in the admin if Gravity Forms needs to reauthenticate with Constant Contact.
	 *
	 * @since 1.6
	 */
	public function display_notices() {
		if ( ! $this->requires_api_reauthentication() ) {
			return;
		}

		$message = sprintf(
		/* translators: 1: open <a> tag, 2: close </a> tag */
			esc_html__(
				'On March 31, 2022, Constant Contact is ending support for their previous authorization management service, which can result in outdated applications no longer being able to connect. %1$sPlease update your Constant Contact application%3$s using %2$sthese instructions%3$s and connect using your new application to ensure the continued functionality of your form feeds.',
				'gravityformsconstantcontact'
			),
			'<a href="' . $this->get_plugin_settings_url() . '">',
			'<a href="https://developer.constantcontact.com/api_guide/auth_update_apps.html">',
			'</a>'
		)
		?>

		<div class="gf-notice notice notice-error">
			<p><?php echo wp_kses( $message, array( 'a' => array( 'href' => true ) ) ); ?></p>
		</div>
		<?php
	}
}
