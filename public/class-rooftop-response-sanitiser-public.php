<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://errorstudio.co.uk
 * @since      1.0.0
 *
 * @package    Rooftop_Response_Sanitiser
 * @subpackage Rooftop_Response_Sanitiser/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Rooftop_Response_Sanitiser
 * @subpackage Rooftop_Response_Sanitiser/public
 * @author     Error <info@errorstudio.co.uk>
 */
class Rooftop_Response_Sanitiser_Public {

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
		 * defined in Rooftop_Response_Sanitiser_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Rooftop_Response_Sanitiser_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/rooftop-response-sanitiser-public.css', array(), $this->version, 'all' );

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
		 * defined in Rooftop_Response_Sanitiser_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Rooftop_Response_Sanitiser_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/rooftop-response-sanitiser-public.js', array( 'jquery' ), $this->version, false );

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
        // if we've got an error here, then we can just return it
        if(is_wp_error($data)){
            return $data;
        }


        // create a new response object without getting the links from the existing $data response
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
    public function sanitise_response($response, $post, $request) {
        $response->data['title'] = $post->post_title;

        $this->remove_attributes($response, $post);

        $this->encode_body($response, $post);
        $this->encode_excerpt($response, $post);

        return $response;
    }

    private function remove_attributes($response, $post){
        unset($response->data['guid']);

        return $response;
    }

    private function encode_body($response, $post) {
        // dont include the WP rendered content (includes all sorts of markup and scripts we dont want)
        unset($response->data['content']['rendered']);
        $response->data['content']['json_encoded'] = $post->post_content;

        return $response;
    }

    private function encode_excerpt($response, $post) {
        // dont include the WP rendered content (includes all sorts of markup and scripts we dont want)
        unset($response->data['excerpt']['rendered']);
        $response->data['excerpt']['json_encoded'] = $post->post_excerpt;

        return $response;
    }


    // sanitise_menu_item_response
    public function sanitise_menu_item_response($item){
        $item['url'] = $item['url'].="?fixme-added-in-class-rooftop-response-sanitiser-public=sanitise_menu_item_response";
        return $item;
    }

    public function prepare_content_urls($response, $post, $request) {
        $content = $response->data['content']['json_encoded'];
        $dom = new DOMDocument();
        @$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);

        // get the links that point to this site - ignore those that point to other url's
        $local_href = $_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'];
        $links = $xpath->query("//a[starts-with(@href, '$local_href')]");

        $count = $links->length -1;
        while($count > -1) {
            $link = $links->item($count);
            $url = parse_url($link->getAttribute('href'));

            $content_type = "";
            if($url['host']==$_SERVER['HTTP_HOST']) {
                $path = array_values(array_filter(explode("/", $url['path'])));

                switch(count($path)){
                    case 0:
                        $content_type = "page";
                        $content_id   = null; // link to the root page
                        break;
                    case 1:
                        $content_type = "page";
                        $content_id   = $path[0];
                        break;
                    case 2:
                        $content_type = "post";
                        $content_id   = $path[1];
                        break;
                    case 3:
                        $content_type = $path[1];
                        $content_id   = $path[2];
                        break;
                }
                $content_text = $link->textContent;

                $placeholder_segments = array('type'=>$content_type, 'text'=>$content_text, 'id'=>$content_id);
                $placeholder_str = "[link ".implode(':', array_map(function ($v, $k) { return $k . '=' . $v; }, $placeholder_segments, array_keys($placeholder_segments)))."]";
                $placeholder = $dom->createTextNode($placeholder_str);
                $link->parentNode->replaceChild($placeholder, $link);
            }

            $count--;
        }

        $response->data['content']['json_encoded'] = $dom->saveHTML();
        return $response;
    }

    
}
