<?php
//XMLSitemapVideoFeed CLASS
class XMLSitemapVideoFeed {

	function go() {		
		global $wpdb;
		if ($wpdb->blogid && function_exists('get_site_option') && get_site_option('tags_blog_id') == $wpdb->blogid) 
		{
			// we are on wpmu and this is a tags blog!
			// create NO sitemap since it will be full 
			// of links outside the blogs own domain...
		} 
		else 
		{
			// INIT
			add_action('init', array(__CLASS__, 'init'));
	
			// FEED
			add_action('do_feed_sitemap-video', array(__CLASS__, 'load_template_sitemap_video'), 10, 1);

			// REWRITES
			add_filter('generate_rewrite_rules', array(__CLASS__, 'rewrite'));
			
			//Envía el ping a Google y Bing
			add_action('enviar_ping', array(__CLASS__, 'EnviaPing'), 10, 1);
			
			//Actúa cuando se publica una página, una entrada o se borra una entrada
			add_action('publish_post', array(__CLASS__, 'ProgramaPing'), 999, 1);
			add_action('publish_page', array(__CLASS__, 'ProgramaPing'), 9999, 1);
			add_action('delete_post', array(__CLASS__, 'ProgramaPing'), 9999, 1);
		}

		// DE-ACTIVATION
		register_deactivation_hook(XMLSVF_PLUGIN_DIR . '/xml-sitemap.php', array(__CLASS__, 'deactivate'));
	}

	// set up the video sitemap template
	function load_template_sitemap_video() {
		load_template(XMLSVF_PLUGIN_DIR . '/feed-sitemap-video.php');
	}

	// REWRITES //
	// add sitemap rewrite rules
	function rewrite($wp_rewrite) {
		$feed_rules = array('sitemap-video.xml$' => $wp_rewrite->index . '?feed=sitemap-video',);
		$wp_rewrite->rules = $feed_rules + $wp_rewrite->rules;
	}

	// DE-ACTIVATION
	function deactivate() {
		remove_filter('generate_rewrite_rules', array(__CLASS__, 'rewrite'));
		delete_option('gn-sitemap-video-feed-mu-version');
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}

	// MULTI-LANGUAGE PLUGIN FILTERS

	// qTranslate
	function qtranslate($input) {
		global $q_config;

		if (is_array($input)) // got an array? return one!
		{
			foreach ($input as $url)
			{
				foreach($q_config['enabled_languages'] as $language) $return[] = qtrans_convertURL($url,$language);
			}
		}
		else $return = qtrans_convertURL($input); // not an array? just convert the string.

		return $return;
	}

	// xLanguage
	function xlanguage($input) {
		global $xlanguage;
	
		if (is_array($input)) // got an array? return one!
		{
			foreach ($input as $url)
			{
				foreach($xlanguage->options['language'] as $language) $return[] = $xlanguage->filter_link_in_lang($url,$language['code']);
			}
		}
	 	else $return = $xlanguage->filter_link($input); // not an array? just convert the string.

		return $return;
	}

	function init() {
		// FLUSH RULES after (site wide) plugin upgrade
		if (get_option('gn-sitemap-video-feed-mu-version') != XMLSVF_VERSION) 
		{
			update_option('gn-sitemap-video-feed-mu-version', XMLSVF_VERSION);
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		}

		// check for qTranslate and add filter
		if (defined('QT_LANGUAGE')) add_filter('xml_sitemap_url', array(__CLASS__, 'qtranslate'), 99);

		// check for xLanguage and add filter
		if (defined('xLanguageTagQuery')) add_filter('xml_sitemap_url', array(__CLASS__, 'xlanguage'), 99);
	}

	//Programa el ping a los buscadores web
	public static function ProgramaPing() {
		wp_schedule_single_event(time(),'enviar_ping');
	}

	//Envía el ping a Google y Bing
	public static function EnviaPing() {
		$ping = array("http://www.google.com/webmasters/sitemaps/ping?sitemap=" . urlencode(home_url('/') . "sitemap-video.xml"), "http://www.bing.com/webmaster/ping.aspx?siteMap=" . urlencode(home_url('/') . "sitemap-video.xml"));

		$options['timeout'] = 10;

		foreach($ping as $url) wp_remote_get($url, $options);
	}
}
