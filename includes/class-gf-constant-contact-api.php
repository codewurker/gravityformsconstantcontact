<?php

if ( ! class_exists( 'GFForms') ) {
	die();
}

/**
 * Gravity Forms Constant Contact Add-On API library.
 *
 * @since     1.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2019, Rocketgenius
 */
class GF_ConstantContact_API {
	/**
	 * Base Constant Contact API URL.
	 *
	 * @since  1.0
	 * @var    string
	 * @access protected
	 */
	protected $api_url = 'https://api.cc.email/v3/';

	/**
	 * Constant Contact authentication data.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    array $auth_token Constant Contact authentication data.
	 */
	protected $auth_token = null;

	/**
	 * When refreshing token fails, the add-on retries to refresh again.
	 * This var stores how many failed tries the add-on made.
	 *
	 * @since 1.7
	 *
	 * @var int $failed_refresh_retries Number of refresh token retries.
	 */
	protected static $failed_refresh_retries = 0;

	/**
	 * Defines how many times the refresh_token() method should retry to refresh within the current request.
	 *
	 * @since 1.7
	 */
	const CC_REFRESH_RETRIES = 2;

	/**
	 * Initialize Slack API library.
	 *
	 * @since  1.0
	 *
	 * @param  array $auth_token Authentication token data.
	 */
	public function __construct( $auth_token = null ) {

		$this->auth_token = $auth_token;

	}

	/**
	 * Make API request.
	 *
	 * @since  1.0
	 *
	 * @param  string $path Request path.
	 * @param  array  $options Request option.
	 * @param  string $method (default: 'GET') Request method.
	 *
	 * @return array|int|WP_Error Results.
	 */
	public function make_request( $path, $options = array(), $method = 'GET' ) {
		$auth_token = $this->auth_token;

		// Get API URL.
		$api_url = $this->api_url;

		// Add options if this is a GET request.
		$request_options = ( 'GET' === $method && ! empty( $options ) ) ? '?' . http_build_query( $options ) : null;

		// Build request URL.
		$request_url = $api_url . $path . $request_options;

		// Build request arguments.
		$args = array(
			'body'    => 'GET' !== $method ? json_encode( $options ) : null,
			'method'  => $method,
			/**
			 * Sets the HTTP timeout, in seconds, for the request.
			 *
			 * @param int    30           The timeout limit, in seconds. Defaults to 30.
			 * @param string $request_url The request URL.
			 *
			 * @return int
			 */
			'timeout' => apply_filters( 'http_request_timeout', 30, $request_url ),
			'headers' => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $auth_token['access_token'],
				'Content-Type'  => 'application/json; charset=' . get_option( 'blog_charset' ),
				'Cache-Control' => 'no-cache',
			),
		);

		// Execute request.
		$response = wp_remote_request( $request_url, $args );

		// If WP_Error, return it. Otherwise, return decoded JSON.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Decode response body.
		$response['body'] = json_decode( $response['body'], true );

		// Get the response code.
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( ! in_array( $response_code, array( 200, 201 ), true ) ) {
			$errors = new WP_Error( 'constantcontact_api_error', wp_remote_retrieve_response_message( $response ), array( 'status' => $response_code ) );

			// Add errors if available.
			if ( isset( $response['body']['error_key'] ) ) {
				$errors->add( 'constantcontact_api_error', $response['body']['error_message'] );
			} elseif ( isset( $response['body'][0]['error_key'] ) ) {
				foreach ( $response['body'] as $body ) {
					$errors->add( 'constantcontact_api_error', $body['error_message'] );
				}
			}

			return $errors;
		}

		// Remove links from response.
		unset( $response['body']['_links'] );

		return $response['body'];
	}

	/**
	 * Get refresh token.
	 *
	 * @since 1.0
	 *
	 * @param string $refresh_token Refresh token.
	 *
	 * @return array|bool|mixed|object
	 */
	public function refresh_token( $refresh_token = '' ) {
		$settings = gf_constantcontact()->get_plugin_settings();

		$auth_token = gf_constantcontact()->get_tokens( $refresh_token );

		if ( ! empty( $auth_token['access_token'] ) && ! empty( $auth_token['refresh_token'] ) ) {
			gf_constantcontact()->log_debug( __METHOD__ . '(): API tokens refreshed successfully.' );

			// Add access token to plugin settings.
			$settings['auth_token'] = array(
				'access_token'  => $auth_token['access_token'],
				'refresh_token' => $auth_token['refresh_token'],
				'date_created'  => time(),
				'expires_in'    => $auth_token['expires_in'],
			);

			unset( $settings['first_refresh_failure_timestamp'], $settings['failed_refresh_attempts'] );

			gf_constantcontact()->update_plugin_settings( $settings );

			$this->auth_token = $auth_token;

			return $auth_token;
		}

		if ( self::$failed_refresh_retries === self::CC_REFRESH_RETRIES ) {
			gf_constantcontact()->log_debug( __METHOD__ . '(): API tokens expired, failed to refresh after re-trying for ' . self::CC_REFRESH_RETRIES . ' times, If the error persists, please make sure the application registered on Constant Contact is only used by this website.' );

			return false;
		}

		gf_constantcontact()->log_debug( __METHOD__ . '(): API tokens expired, failed to refresh. retrying to refresh, retries remaining: ' . ( self::CC_REFRESH_RETRIES - self::$failed_refresh_retries ) );
		self::$failed_refresh_retries += 1;
		// To space out the requests, delay the execution by an interval that increases after every try.
		sleep( 2 * self::$failed_refresh_retries );
		gf_constantcontact()->log_debug( __METHOD__ . '(): Calling refresh again after waiting for ' . ( 2 * self::$failed_refresh_retries . ' seconds' ) );

		return $this->refresh_token( $refresh_token );
	}

	/**
	 * Get contacts.
	 *
	 * @since  1.0
	 *
	 * @param array $options API options.
	 *
	 * @return array|WP_Error
	 */
	public function get_contacts( $options = array() ) {
		$results = $this->make_request( 'contacts', $options );

		return ( ! is_wp_error( $results ) && isset( $results['contacts'] ) ) ? $results['contacts'] : $results;
	}

	/**
	 * Get lists.
	 *
	 * @since 1.0
	 *
	 * @return array|int|WP_Error
	 */
	public function get_lists() {
		return $this->make_request( 'contact_lists', array( 'include_count' => true ) );
	}

	/**
	 * Get a list.
	 *
	 * @since 1.0
	 *
	 * @param string $list_id List id.
	 *
	 * @return array|bool|WP_Error
	 */
	public function get_list( $list_id ) {
		return $this->make_request( "contact_lists/{$list_id}" );
	}

	/**
	 * Check whether a subscriber exists at $email
	 *
	 * @since 1.0
	 *
	 * @param string $email Email address.
	 *
	 * @return bool|array|WP_Error False if not found; array contact details if found.
	 */
	public function contact_exists( $email = '' ) {

		if ( GFCommon::is_invalid_or_empty_email( $email ) ) {
			return false;
		}

		$result = $this->get_contact_details( $email );

		if ( is_wp_error( $result ) ) {
			gf_constantcontact()->log_debug( __METHOD__ . '(): Cannot check with the API if the contact exists. Error: ' . $result->get_error_message() );

			return $result;
		}

		return empty( $result ) ? false : $result;
	}

	/**
	 * Get subscriber details.
	 *
	 * @since 1.0
	 *
	 * @param string $email Email address.
	 * @param string $include Specify which contact subresources to include in the response.
	 *
	 * @return array|false|WP_Error False if invalid email or contact not found. Array of contact details if found.
	 */
	public function get_contact_details( $email = '', $include = 'list_memberships' ) {

		if ( GFCommon::is_invalid_or_empty_email( $email ) ) {
			return false;
		}

		$results = $this->get_contacts(
			array(
				'email'   => $email,
				'include' => $include,
			)
		);

		if ( is_wp_error( $results ) ) {
			return $results;
		}

		return empty( $results ) ? false : $results[0];
	}

	/**
	 * Add or update contact details.
	 *
	 * @since 1.0
	 *
	 * @param array       $contact_details Contact details.
	 * @param bool|string $contact_id      Contact ID.
	 *
	 * @return array|WP_Error Results.
	 */
	public function update_contact( $contact_details = array(), $contact_id = false ) {
		if ( ! $contact_id ) {
			$contact_details['create_source'] = 'Contact';
			return $this->make_request( 'contacts', $contact_details, 'POST' );
		} else {
			$contact_details['update_source'] = 'Contact';
			return $this->make_request( 'contacts/' . $contact_id, $contact_details, 'PUT' );
		}
	}

	/**
	 * Get contact custom fields.
	 *
	 * @since 1.0
	 *
	 * @return array|WP_Error Results.
	 */
	public function get_custom_fields() {
		return $this->make_request( 'contact_custom_fields' );
	}
}
