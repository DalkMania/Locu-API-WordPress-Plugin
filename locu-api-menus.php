<?php
/*
Plugin Name: Locu Menu Plugin
Description: Locu Menu Plugin that pulls the establishments menu and business information via version 2 of Locu's API and displays it on a WordPress based website. Perfect for owners of restaurants and other places, that uses Locu for their menus. 
Plugin URI: http://www.niklasdahlqvist.com
Author: Niklas Dahlqvist
Author URI: http://www.niklasdahlqvist.com
Version: 0.8.0
Requires at least: 4.2
License: GPL
*/

/*
   Copyright 2015  Niklas Dahlqvist  (email : dalkmania@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
* Ensure class doesn't already exist
*/
if(! class_exists ("Locu_Menu_Plugin") ) {

  class Locu_Menu_Plugin {
    private $options;
    private $apiBaseUrl;

    /**
     * Start up
     */
    public function __construct()
    {
        $this->options = get_option( 'locu_settings' );;
        $this->apiBaseUrl = 'https://api.locu.com/v2';
        $this->api_key = $this->options['locu_api_key'];
        $this->establishment_name = $this->options['locu_establishment_name'];
        $this->locu_id = $this->options['locu_id'];



        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );

        add_action('admin_print_styles', array($this,'plugin_admin_styles'));
        add_action( 'admin_enqueue_scripts', array( $this, 'plugin_admin_js' ) );
        
        add_action( 'wp_ajax_locu_ajax_look_up', array( $this, 'locu_ajax_look_up' ));
        add_action( 'wp_ajax_nopriv_locu_ajax_look_up', array( $this,'locu_ajax_look_up') );
        add_action( 'wp_enqueue_scripts',  array( $this,'plugin_frontend_js') );
        add_shortcode('locu_menu', array( $this,'MenuShortCode') );


    }

    public function plugin_admin_styles() {
        wp_enqueue_style('admin-style', $this->getBaseUrl() . '/assets/css/plugin-admin-styles.css');

    }

    public function plugin_admin_js() {
        wp_register_script( 'admin-js', $this->getBaseUrl() . '/assets/js/plugin-admin-scripts.js' );
        wp_enqueue_script( 'admin-js' );
    }

    public function plugin_frontend_js() {
        wp_register_script( 'frontend-script', $this->getBaseUrl() . '/assets/js/plugin-frontend-scripts.js' );
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'frontend-script' );
    }

    public function locu_ajax_look_up() {
        $response = array();
        $nonce = $_POST['nonce'];

        if ( !wp_verify_nonce( $nonce, 'locu_ajax_nonce')) {
            exit('No naughty business');
        } else {
          $api_key = $_POST['data']['api_key'];
          $name = $_POST['data']['name'];

           $locu_id = $this->getBusinessIdFromName($name);

           $response['message'] = __( 'Settings saved', 'framework' );
           $response['locu_id'] = $locu_id;   
           echo json_encode($response);

        }

        die();
    }

    

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_management_page(
            'Locu Settings Admin', 
            'Locu Settings', 
            'manage_options', 
            'locu-settings-admin', 
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'locu_settings' );
        ?>
        <div class="wrap locu-settings">
            <h2>Locu Settings</h2>           
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'locu_settings_group' );   
                do_settings_sections( 'locu-settings-admin' );
                $this->add_locu_ajax_security_field();
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'locu_settings_group', // Option group
            'locu_settings', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'locu_section', // ID
            'Locu Settings', // Title
            array( $this, 'print_section_info' ), // Callback
            'locu-settings-admin' // Page
        );  

        add_settings_field(
            'locu_api_key', // ID
            'Locu API Key', // Title 
            array( $this, 'locu_api_key_callback' ), // Callback
            'locu-settings-admin', // Page
            'locu_section' // Section           
        );      

        add_settings_field(
            'locu_establishment_name', 
            'Locu Establishment Name', 
            array( $this, 'locu_establishment_name_callback' ), 
            'locu-settings-admin', 
            'locu_section'
        );

        add_settings_field(
            'locu_id', 
            'Locu ID', 
            array( $this, 'locu_id_callback' ), 
            'locu-settings-admin', 
            'locu_section'
        );      
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();
        if( isset( $input['locu_api_key'] ) )
            $new_input['locu_api_key'] = sanitize_text_field( $input['locu_api_key'] );

        if( isset( $input['locu_establishment_name'] ) )
            $new_input['locu_establishment_name'] = sanitize_text_field( $input['locu_establishment_name'] );

        if( isset( $input['locu_id'] ) )
            $new_input['locu_id'] = sanitize_text_field( $input['locu_id'] );

        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        print 'Enter your settings below:';

    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function locu_api_key_callback()
    {
        printf(
            '<input type="text" id="locu_api_key" class="regular-text" name="locu_settings[locu_api_key]" value="%s" />',
            isset( $this->options['locu_api_key'] ) ? esc_attr( $this->options['locu_api_key']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function locu_establishment_name_callback()
    {
        printf(
            '<input type="text" id="locu_establishment_name" class="regular-text" name="locu_settings[locu_establishment_name]" value="%s" />',
            isset( $this->options['locu_establishment_name'] ) ? esc_attr( $this->options['locu_establishment_name']) : ''
        );
    }

    public function locu_id_callback()
    {
        printf(
            '<input type="text" id="locu_id" name="locu_settings[locu_id]" value="%s" />',
            isset( $this->options['locu_id'] ) ? esc_attr( $this->options['locu_id']) : ''
        );

        print '<input type="submit" value="Look up Locu ID" class="button button-primary" id="lookup" name="lookup">';
    }

    public function add_locu_ajax_security_field () {
      print '<input type="hidden" id="ajaxsecurity" name="security" value="' . wp_create_nonce( 'locu_ajax_nonce' ) . '" />';
    }

    // Send Curl Request to Locu Endpoints and return the response
    public function sendRequest( $data, $call ) { 
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $this->apiBaseUrl.$call);
      curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
      $response = json_decode(curl_exec($ch),true);
      return $response;
    }

    public function getBusinessIdFromName($name, $endpoint = '/venue/search') {

      $packet = array(
        "api_key" => $this->api_key,
        "fields" => array("locu_id", "name"),
        "venue_queries" => array(
          array(
            "name" => stripcslashes($name),
          ),
        ),        
      );

      $response = $this->sendRequest($packet,$endpoint);

      return $response['venues'][0]['locu_id'];

    }

    public function getBusinessInformation($endpoint = '/venue/search' ) {

      $packet = array(
        "api_key" => $this->api_key,
        "fields" => array(
          "name",
          "description",
          "website_url",
          "location",
          "contact",
          "open_hours",
          "extended",
          "description",
          "short_name"
        ),
        "venue_queries" => array(
          array(
            "locu_id" => $this->locu_id
          ),
        ),        
      );

    }

    public function getBusinessMenus( $endpoint = '/venue/search' ) {

      $packet = array(
        "api_key" => $this->api_key,
        "fields" => array("menus"),
        "venue_queries" => array(
          array(
            "locu_id" => $this->locu_id
          ),
        ),        
      );

      $response = $this->sendRequest($packet,$endpoint);

      return $response['venues'][0]['menus'];
      
    }

    public function storeBusinnessInformation($locu_data) {

      // Get any existing copy of our transient data
      if ( false === ( $locu_business_info = get_transient( 'locu_business_information' ) ) ) {
        // It wasn't there, so regenerate the data and save the transient for 12 hours
        $locu_business_info = $locu_data;
        set_transient( 'locu_business_information', $locu_business_information, 12 * HOUR_IN_SECONDS );
      }

    }

    public function storeBusinnessMenus($locu_data) {

      // Get any existing copy of our transient data
      if ( false === ( $locu_business_menus = get_transient( 'locu_business_menus' ) ) ) {
        // It wasn't there, so regenerate the data and save the transient for 2 hours
        $locu_business_menus = serialize($locu_data);
        set_transient( 'locu_business_menus', $locu_business_menus, 2 * HOUR_IN_SECONDS );
      }
      
    }

    public function flushStoredInformation() {
      //Delete transients to force a new pull from the API
      delete_transient( 'locu_business_menus' );
      delete_transient( 'locu_business_information' );
    }

    public function MenuShortCode($atts, $content = null) {
      extract(shortcode_atts(array(
        'menu_id' => '',
        'layout' => '',
        ), $atts));

      $output = '';

      $menus = $this->get_menu_sections();

      //print_r($menus);

      if(is_array($menus) && !empty($menus)) {
        $output .= '<div class="locu-outer">';
        $output .= '<ul class="locu-nav">';     
        foreach ($menus as $menu) {
          $output .= '<li class="locu-tab" data-tab="' . sanitize_title( $menu['menu_name'] ) . '">' . $menu['menu_name'] . '</li>';
        }
        $output .= '</ul>';

        $output .= '<div typeof="schema:OfferAggregate" xmlns:v="http://rdf.data-vocabulary.org/#" class="locu-menus">';

        foreach ($menus as $menu) {
          $output .= '<div id="' . sanitize_title( $menu['menu_name'] ) . '" class="locu-menu locu-panel">';
          $output .= '<div class="locu-menu-name">' . $menu['menu_name'] . '</div>';

          if(is_array($menu['menu_sections']) && !empty($menu['menu_sections'])) {
            $i = 0;
            foreach ($menu['menu_sections'] as $section) {
              $output .= '<div class="locu-section-name">';
              $output .= '<span class="section-name">'. $section .'</span>';
              $output .= '</div>';

              $output .= '<div class="locu-subsection">';

              foreach ($menu['menu_content'][$i] as $c) {

                if(isset($c['text']) && $c['text'] !='') {
                  $output .= '<div class="locu-note">'. $c['text'] .'</div>';
                }
        
                if(isset($c['name']) && $c['name'] !='') {
                  $output .= '<div typeof="schema:Offer" class="locu-menu-item">';
                  $output .= '<div typeof="schema:name" class="locu-menu-item-name">'. $c['name'].'</div>';
                
                  if(isset($c['price']) && $c['price'] !='') {
                    $output .= '<div typeof="schema:price" class="locu-menu-item-price">'. $c['price'] .'</div>';
                  }
                  if(isset($c['description']) && $c['description'] !='') {
                    $output .='<div typeof="schema:description" class="locu-menu-item-description">'. $c['description'] .'</div>';
                  }

                  if(isset($c['option_groups']) && is_array($c['option_groups']) && !empty($c['option_groups']) ) {
                    $output .= '<div class="option-wrapper">';
                    foreach ($c['option_groups'] as $group) {
                      $output .= '<div class="option-group">';
                      $output .= '<div class="option-group-inner">'. $group['text'] .'</div>';
                      foreach ($group['options'] as $option) {
                        $output .= '<div class="option">';
                        $output .= '<div class="option-name">'. $option['name'] . '</div>';
                        $output .= '<div class="option-price">'. $option['price'] .'</div>';
                        $output .= '</div>';
                      }
                    }
                  }
                  $output .= '</div>';
                }
              }

              $output .= '</div>';
              $i++;
            }
            $output .= '</div>';
          } else {
            $i = 0;
            $output .= '<div class="locu-subsection">';

            foreach ($menu['menu_content'][$i] as $c) {

              if(isset($c['text']) && $c['text'] !='') {
                $output .= '<div class="locu-note">'. $c['text'] .'</div>';
              }

              if(isset($c['name']) && $c['name'] !='') {
                  $output .= '<div typeof="schema:Offer" class="locu-menu-item">';
                  $output .= '<div typeof="schema:name" class="locu-menu-item-name">'. $c['name'].'</div>';
                
                  if(isset($c['price']) && $c['price'] !='') {
                    $output .= '<div typeof="schema:price" class="locu-menu-item-price">'. $c['price'] .'</div>';
                  }
                  if(isset($c['description']) && $c['description'] !='') {
                    $output .='<div typeof="schema:description" class="locu-menu-item-description">'. $c['description'] .'</div>';
                  }

                  if(isset($c['option_groups']) && is_array($c['option_groups']) && !empty($c['option_groups']) ) {
                    $output .= '<div class="option-wrapper">';
                    foreach ($c['option_groups'] as $group) {
                      $output .= '<div class="option-group">';
                      $output .= '<div class="option-group-inner">'. $group['text'] .'</div>';
                      foreach ($group['options'] as $option) {
                        $output .= '<div class="option">';
                        $output .= '<div class="option-name">'. $option['name'] . '</div>';
                        $output .= '<div class="option-price">'. $option['price'] .'</div>';
                        $output .= '</div>';
                      }
                      $output .= '</div>';
                    }
                  }
                  $output .= '</div>';
                }

            }

            $output .= '</div>';
            $output .= '</div>';
            $i++;
          }

        }
        $output .= '</div>';
      }

      $output .= '<a class="locu-link" href="http://locu.com"><img src="http://locu.com/static/images/dev/poweredby-black@2x.png" alt="Powered by Locu" /></a>';
      $output .= '</div>';
      return $output;
    }

    public function get_menu_sections() {

      // Get any existing copy of our transient data
      if ( false === ( $locu_data = get_transient( 'locu_business_menus' ) ) ) {
        // It wasn't there, so make a new API Request and regenerate the data
        $menu = $this->getBusinessMenus();
          
        $locu_data = array();

        foreach ($menu as $item) {
          
          if(is_array($item['sections']) && !empty($item['sections'])) {
            $sections = array();
            $subsections = array();
             foreach($item['sections'] as $section){
              if(!empty($section['section_name'])) {
                $sections[] = $section['section_name'];
              }
            }
          }

          if(is_array($item['sections']) && !empty($item['sections'])) {
            foreach($item['sections'] as $section){
              foreach($section['subsections'] as $subsection){
                $subsections[] = $subsection['contents'];
              }
            }
          }

          $menu_item = array(
            'menu_name' => $item['menu_name'],
            'menu_sections' => $sections,
            'menu_content' => $subsections
          );

          array_push($locu_data, $menu_item);

        }

        // It wasn't there, so save the transient for 2 hours
        $this->storeBusinnessMenus($locu_data);

      } else {
        // Get any existing copy of our transient data
        $locu_data = unserialize(get_transient( 'locu_business_menus' ));
      }

      // Finally return the data
      return $locu_data;
    }


    // For later implementation
    public function customShortCodeScripts() {
      global $post;
      if( has_shortcode( $post->post_content, 'locu_menu') ) {
        wp_enqueue_script( 'frontend-script');
      }
    }

   //Returns the url of the plugin's root folder
    protected function getBaseUrl(){
      return plugins_url(null, __FILE__);
    }

    //Returns the physical path of the plugin's root folder
    protected function getBasePath(){
      $folder = basename(dirname(__FILE__));
      return WP_PLUGIN_DIR . "/" . $folder;
    }


  } //End Class

  /**
   * Instantiate this class to ensure the action and shortcode hooks are hooked.
   * This instantiation can only be done once (see it's __construct() to understand why.)
   */
  new Locu_Menu_Plugin();

} // End if class exists statement