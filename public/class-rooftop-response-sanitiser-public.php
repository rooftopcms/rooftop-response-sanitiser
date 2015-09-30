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

        $this->return_link_object($response);

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
        $item['url'] = $this->parse_url($item['url'], $stringify_ancestors=false);
        return $item;
    }

    public function prepare_content_urls($response, $post, $request) {
        $content = $response->data['content']['json_encoded'];
        $content_wrapped = "<span id='rooftop-content-wrapper'>".$content."</span>";

        $dom = new DOMDocument();
        @$dom->loadHTML($content_wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);

        // get the links that point to this site - ignore those that point to other url's
        $local_href = $_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'];
        $links = $xpath->query("//a[starts-with(@href, '$local_href')]");

        $count = $links->length -1;
        while($count > -1) {
            $link = $links->item($count);

            $placeholder_shortcode = $this->parse_url($link->getAttribute('href'));
            if(is_array($placeholder_shortcode)){
                $placeholder_shortcode['content'] = $link->textContent; // also include the link text as part of the shortcode
                $placeholder_shortcode_str = "[link ".implode(':', array_map(function ($v, $k) { return $k . '=' . $v; }, $placeholder_shortcode, array_keys($placeholder_shortcode)))."]";
                $placeholder = $dom->createTextNode($placeholder_shortcode_str);
                $link->parentNode->replaceChild($placeholder, $link);
            }


            $count--;
        }

        // get the content wrapper and return its html content
        $html = '';
        $el = $dom->getElementById('rooftop-content-wrapper');
        foreach($el->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        $response->data['content']['json_encoded'] = $html;
        return $response;
    }

    function return_link_object($response) {
        $url_object = $this->parse_url($response->data['link'], $stringify_ancestors=false);

        return $response->data['link'] = $url_object;
    }

    private function parse_url($_url, $stringify_ancestors=true) {
        $post_id = url_to_postid($_url);
        $url = parse_url($_url);
        $internal_link = $url['host'] == $_SERVER['HTTP_HOST'];

        if($post_id) {
            $post = get_post($post_id);
            $ancestors = get_ancestors($post_id, $post->post_type);

            $content_type = $post->post_type;
            $content_id   = $post->ID;

            $shortcode_attributes = array('type'=>$content_type, 'id'=>$content_id);

            if(count($ancestors)){
                $shortcode_attributes['ancestors'] = $stringify_ancestors ? implode(',', array_reverse($ancestors)) : array_reverse($ancestors);
            }

            return $shortcode_attributes;
        }elseif($internal_link) {
            $front_page = array('type' => 'relative', 'path' => $url['path']);

            return $front_page;
        }else {
            return $_url;
        }

    }
}
