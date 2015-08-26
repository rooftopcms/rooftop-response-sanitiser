<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://errorstudio.co.uk
 * @since      1.0.0
 *
 * @package    Justified_Response_Sanitiser
 * @subpackage Justified_Response_Sanitiser/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Justified_Response_Sanitiser
 * @subpackage Justified_Response_Sanitiser/public
 * @author     Error <info@errorstudio.co.uk>
 */
class Justified_Response_Sanitiser_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Justified_Response_Sanitiser_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Justified_Response_Sanitiser_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/justified-response-sanitiser-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Justified_Response_Sanitiser_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Justified_Response_Sanitiser_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/justified-response-sanitiser-public.js', array( 'jquery' ), $this->version, false );

	}

    /**
     * @param $data
     * @return WP_REST_Response
     *
     * Remove the _links attribute from our response by duplicating the
     * WP_REST_Response object by creating a new one and copying the attributes (but not links)
     *
     */
    public function sanitise_response_remove_links($data) {
        $new_response = new WP_REST_Response();
        $new_response->set_matched_route($data->get_matched_route());
        $new_response->set_matched_handler($data->get_matched_handler());
        $new_response->set_headers($data->get_headers());
        $new_response->set_status($data->get_status());
        $new_response->set_data($data->get_data());

        return $new_response;
    }

    /**
     * @param $data
     * @param $post
     * @param $request
     * @return mixed
     *
     * Cleanup the response object by removing some fields that we dont want, and including some new ones.
     * ie. we remove the rendered html content and include a json_encoded version from the post->post_content
     *
     */
    public function sanitise_response($data, $post, $request) {
        // dont include the WP rendered content (includes all sorts of markup and scripts we dont want)
        unset($data->data['content']['rendered']);

        $this->encode_body($data, $post);

        return $data;
    }

    private function encode_body($data, $post) {
        $data->data['content']['json_encoded'] = json_encode($post->post_content);

        return $data;
    }
}
