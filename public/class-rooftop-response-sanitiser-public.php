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


    /**
     *
     * Temporarily store the link of the requested resource in this variable so that we're not re-writing
     * the link attribute more than once and causing issues later in the WP response (ie re-writing)...
     *
     * @var
     */
    private $tmp_link;


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
     * Hook callback - called in rest_api_init()
     */
    public function prepare_content_response_hooks() {
        // register hooks for specific post types
        $types = get_post_types(array('public' => true));

        foreach($types as $key => $type) {
            add_action( "rest_prepare_$type", array( $this, 'sanitise_response' ), 10, 3 );
        }
    }

    /**
     * @param $response
     * @param $post
     * @param $request
     * @internal param $data
     * @return mixed
     *
     * Cleanup the response object by removing some fields that we dont
     * want, and json-encoding some others (like the link attribute)
     */
    public function sanitise_response($response, $post, $request) {
        if( is_wp_error($response) ) {
            return $response;
        }

        // move the content attributes into a content[basic/advanced][content/fields] structure
        apply_filters( 'rooftop_restructure_post_response', $response, $post );

        // return the link attribute as a json object of post type and id
        if( ! $this->tmp_link ) {
            $this->tmp_link = $response->data['link'];
        }

        $response->data['link'] = apply_filters( 'rooftop_return_link_as_object', $this->tmp_link );

        return $response;
    }

    /**
     * @param $response
     * @param $post
     *
     * remove the content and excerpt attributes from the content
     * field and into a nested structure like this:
     *
     * content: {
     *   basic: {
     *     content: "...",
     *     excerpt: "..."
     *   }
     * }
     *
     */
    function restructure_post_response($response, $post) {
        // remove the rendered html version of the content and excerpt
        unset($response->data['content']);
        unset($response->data['excerpt']);

        $response->data['content'] = array('basic' => array());
        $response->data['content']['basic']['content'] = apply_filters( 'rooftop_sanitise_html', $post->post_content );
        $response->data['content']['basic']['excerpt'] = apply_filters( 'rooftop_sanitise_html', $post->post_excerpt );

        if( array_key_exists( 'title', $response->data ) && array_key_exists( 'rendered', $response->data['title'] ) ) {
            $response->data['title'] = $response->data['title']['rendered'];
        }
    }

    /**
     * @param $link
     * @internal param $response
     * @return array
     *
     * return a links href attribute and return a json object:
     *
     * 'http://foo.bar.com/posts/12' => {type: 'post', id: 5} (includes an array of ancestors if necessary)
     */
    function return_link_as_object($link) {
        $url_object = $this->parse_url($link, $stringify_ancestors=false);

        return $url_object;
    }

    /**
     * @param $item
     * @return mixed
     *
     * for a given menu item, parse the url and return a json object with the
     * required attributes to build a valid link in the client
     */
    public function sanitise_menu_item_response($item){
        $item['url'] = $this->parse_url($item['url'], $stringify_ancestors=false);
        return $item;
    }

    /**
     * @param $html
     * @return string
     *
     * parse the html snippet for internal links and replace them with
     * shortcodes that we can render in a url-agnostic way in the client
     *
     * apply_filters ( 'rooftop_sanitise_html', "some html" );
     *
     */
    public function sanitise_html($html) {
        $content_wrapped = "<span id='rooftop-content-wrapper'>".$html."</span>";

        $dom = new DOMDocument();
        @$dom->loadHTML($content_wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);

        // get the links that point to this site - ignore those that point to other url's
        $local_href = "http://".$_SERVER['HTTP_HOST'];
        $links = $xpath->query("//a[starts-with(@href, '$local_href')]");

        $count = $links->length -1;
        while($count > -1) {
            $link = $links->item($count);
            // parse the link and generate an array of keys and values
            $linkData = $this->parse_url($link->getAttribute('href'),false);
            if(is_array($linkData)){
               // create a new <a> and add data attributes to it.
               $linkNode = $dom->createElement('a',$link->textContent);

                //split the path up. In the case of an archive link we need to point to the correct post type;
                //in the case of something with ancestors, we need to derive the ancestor slugs
                $pathData = explode("/",$linkData['path']);
                // if this is a link to a custom post type, WP will return a path
                // to an archive page which isn't much use. We standardise that into type / slug
                if($linkData['type'] == "relative") {
                    // /archives/foo/bar where foo is post type and bar is slug
                    $linkData['type'] = $pathData[2];
                    $linkData['slug'] = $pathData[3];
                } elseif(array_key_exists('ancestors',$linkData)) {
                    // this has ancestors, and we need to grab the slugs
                    $ancestorCount = count($linkData['ancestors']);
                    $ancestorSlugs = array_slice($pathData, -$ancestorCount-1,-1);
                    $linkData['ancestor-slugs'] = implode(",",$ancestorSlugs);
                    $linkData['ancestor-ids'] = implode(",", $linkData['ancestors']);
                }

                // We don't need the raw path, which makes no sense for consumers
                unset($linkData['path']);
                // We don't need the array of ancestors
                unset($linkData['ancestors']);

                $d = $linkData;

                foreach($linkData as $k => $v) {
                    $attr = $dom->createAttribute("data-rooftop-link-".$k);
                    $attr->value = $v;
                    $linkNode->appendChild($attr);
                }
                $link->parentNode->replaceChild($linkNode, $link);
            }

            $count--;
        }

        // get the content wrapper and return its html content
        $html = '';
        $el = $dom->getElementById('rooftop-content-wrapper');
        foreach($el->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        $parsed_html = do_shortcode($html);
        
        return $parsed_html;
    }

    public function prepare_rest_menu_item($menu_item) {
        $menu_item['type'] = 'menu';
        $menu_item['id'] = $menu_item['ID'];
        unset($menu_item['ID']);
        return $menu_item;
    }
    public function prepare_rest_menu_items($menu_items) {
        $menu_items = array_map(function($mi) {
            $mi['type'] = 'menu';
            $mi['id'] = $mi['ID'];
            unset($mi['ID']);



            return $mi;
        }, $menu_items);
        return $menu_items;
    }

    /**
     * @param $_url
     * @param bool $stringify_ancestors
     * @return array
     *
     *
     * helper to parse a url to return its content type and ID as a json object
     */
    private function parse_url($_url, $stringify_ancestors=true) {
        $post_id = url_to_postid($_url);
        $url = parse_url($_url);
        $internal_link = $url['host'] == $_SERVER['HTTP_HOST'];

        if($post_id) {
            $post = get_post($post_id);
            $ancestors = get_ancestors($post_id, $post->post_type);

            $content_type = $post->post_type;
            $content_id   = $post->ID;
            $slug         = $post->post_name;

            $shortcode_attributes = array('type'=>$content_type, 'id'=>$content_id, 'slug'=>$slug,'path' => $url['path']);

            if(count($ancestors)) {
                $shortcode_attributes['ancestors'] = $stringify_ancestors ? implode(',',
                    array_reverse($ancestors)) : array_reverse($ancestors);
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
