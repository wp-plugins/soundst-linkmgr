<?php
/**
 *Plugin Name: SoundSt LinkMgr
 *Plugin URI: http://www.linkmgr.net/
 *Description: Displays category and link information from the Sound Strategies LinkMgr application as on-page embedded content and as a widget.
 *Version: 2.0.0
 *Author: Sound Strategies, Inc
 *Author URI: http://www.soundst.com/
 */

// Set global var
global $linkmanager_url; // path to site: LinkManger system
$linkmanager_url = 'http://linkmgr.net/';

global $linkmanager_plugin_main_file; // path to main file of this plugin
$linkmanager_plugin_main_file = 'linksmanager/links-manager.php';

global $linkmanager_description; // plugin description
$linkmanager_description = '';

global $linkmanager_request; // result of plugin post request
$linkmanager_request = array();

global $linkmanager_on; // indicator of linksmanager plugin state
$linkmanager_on = false;

global $linkmanager_version; // version of this plugin
$linkmanager_version = '1.9';

// link require files(functions)
require_once (WP_PLUGIN_DIR.'/linksmanager/links-functions.php');
// add rewrite rules
require_once (WP_PLUGIN_DIR.'/linksmanager/links-manager-rewrite-rules.php');

//need to output meta in right way
global $meta_outputed;
$meta_outputed = false;

/**
 * INSTALL:
 */
function linksmanager_install(){
	if (get_option('lm_url')===false) add_option('lm_url', 'linksmanager'); // add linksmanager page name option
	if (get_option('lm_login')===false) add_option('lm_login', ''); // add Login name option
	if (get_option('lm_site_ID')===false) add_option('lm_site_ID', 0); // add site ID option
	if (get_option('lm_permalnk')===false) add_option('lm_permalnk', 'Default'); // add site ID option
}


/**
 * UNINSTALL:
 */
function linksmanager_uninstall(){
	/*
	 delete_option('lm_url'); // delete linksmanager page name option
	 delete_option('lm_login'); // delete Login name option
	 delete_option('lm_site_ID'); // delete site ID option
	 delete_option('lm_permalnk'); // add site ID option
	 */
}


/**
 * UPDATE:
 */
function linksmanager_update(){
	global $linkmanager_url; //path to linkmanager
	global $linkmanager_description; // plugin description
	global $linkmanager_plugin_main_file; // path to main file
	global $linkmanager_version; // current version
	global $wp_version; // wordpress version

	$linksmanager_site_trancident_on = linksmanager_wp_version_validate($wp_version);

	if($linksmanager_site_trancident_on) {
		$temp_options = get_site_transient('update_plugins'); // get values of WP 'update_plugins'
	}else{
		$temp_options = get_transient('update_plugins'); // get values of WP 'update_plugins'
	}

	// Get admin current admin page
	$t_wp_url = parse_url($_SERVER['REQUEST_URI']);
	$t_wp_url = explode('/', $t_wp_url['path']);
	$wp_admin_page = $t_wp_url[count($t_wp_url)-1];

	//---------------Make PHP request------------------
	$cansend = true; // indicator of correct plugin settings
	$success_request = true; // indicator of success request

	if (get_option('lm_site_ID')>0 && ($wp_admin_page == 'plugins.php' || $wp_admin_page == 'plugin-install.php' || $wp_admin_page == 'update.php')) { // check if ID of site is set on
		$Site = get_option('lm_site_ID');
	}else{
		$cansend = false;
		$success_request = false;
	}

	if($cansend && !isset($_GET['action']) && !isset($_POST['action'])){
		$URL = $linkmanager_url.'index.php?section=links';
		$ContactURL = $linkmanager_url.'index.php?section=contact';

		$_GET['action'] = 'update'; // request to update plugin
		$_POST['action'] = 'update';
		$_POST['WP'] = 'true';// Set that param... to tell API system: this is WP send you request

		$RequestData = array('XMLRequest' => true, 'Site' => $Site, 'GET' => $_GET, 'POST' => $_POST);
		$ResponseData = linksmanager_file_post_contents($URL, linksmanager_http_parse_query($RequestData));

		// First parsing result of request
		//		ob_start();
		if( function_exists( 'domxml_open_mem' ) ){
			if ($dom = domxml_open_mem($ResponseData)){
				$root = $dom->document_element();

				foreach ($root->get_elements_by_tagname("version") as $Node){
					$linkmanager_request['version'] = base64_decode($Node->get_content());
				}

				foreach ($root->get_elements_by_tagname("path") as $Node){
					$linkmanager_request['path'] = base64_decode($Node->get_content());
				}

				foreach ($root->get_elements_by_tagname("description") as $Node){
					$linkmanager_request['description'] = base64_decode($Node->get_content());
				}
			}
		} else {
			$objXml= new DOMDocument();
			if ($objXml->loadXML( $ResponseData )){

				foreach ($objXml->documentElement->getElementsByTagName("version") as $Node){
					$linkmanager_request['version'] = base64_decode($Node->nodeValue);
				}

				foreach ($objXml->documentElement->getElementsByTagName("path") as $Node){
					$linkmanager_request['path'] = base64_decode($Node->nodeValue);
				}

				foreach ($objXml->documentElement->getElementsByTagName("description") as $Node){
					$linkmanager_request['description'] = base64_decode($Node->nodeValue);
				}
			}
		}
		//		ob_end_clean();

		// success request?
		if(($linkmanager_request['version'] != '')&&($linkmanager_request['path'] != '')){

			// details parse result
			$linkmanager_last_version = $linkmanager_request['version'];
			$linkmanager_update_path = $linkmanager_request['path'];
			$linkmanager_description = $linkmanager_request['description'];

			// test for new version...
			if((float)$linkmanager_last_version > (float)$temp_options->checked[$linkmanager_plugin_main_file]){

				// if transient('update_plugins') already have object with our plugin name
				if(isset($temp_options->response[$linkmanager_plugin_main_file])){

					// if last plugin version > then new_version in transient('update_plugins') object
					if((float)$linkmanager_last_version > (float)$temp_options->response[$linkmanager_plugin_main_file]->new_version){

						// set new values of object parameters
						$temp_options->response[$linkmanager_plugin_main_file]->slug = 'linksmanager';
						$temp_options->response[$linkmanager_plugin_main_file]->new_version = $linkmanager_last_version;
						$temp_options->response[$linkmanager_plugin_main_file]->package = $linkmanager_update_path;
					}
				}else{
					// if object not set yet... create new object
					$new_option = new stdClass();
					$new_option->slug = 'linksmanager';
					$new_option->new_version = $linkmanager_last_version;
					$new_option->package = $linkmanager_update_path;
					$temp_options->response[$linkmanager_plugin_main_file] = $new_option;
				}
				// update WP transient('update_plugins')
				if($linksmanager_site_trancident_on) {
					set_site_transient('update_plugins', $temp_options);
				}else{
					set_transient('update_plugins', $temp_options);
				}

			} else {
				$success_request = false;
			}
		}else{
			$success_request = false;
		}
	}
	if(!$success_request){ // if not success request... then delete WP transient('update_plugins') object(if it is set)
		if(isset($temp_options->response[$linkmanager_plugin_main_file])){
			unset($temp_options->response[$linkmanager_plugin_main_file]);
			if($linksmanager_site_trancident_on) {
				set_site_transient('update_plugins', $temp_options);
			}else{
				set_transient('update_plugins', $temp_options);
			}
		}
	}
}


/**
 * UPDATE INFO:
 */
function linksmanager_update_info(){
	global $linkmanager_description; // plugin description
	global $linkmanager_plugin_main_file; // path to main file of this plugin

	if(($_GET['plugin'] == 'linksmanager')&&($_GET['action'] == 'update')){

		// poste plugin description and update proposition
		printf(__('<a href="%1$s">UPGRATE PLUGIN</a>'), wp_nonce_url('update.php?action=upgrade-plugin&plugin='.$linkmanager_plugin_main_file, 'upgrade-plugin_'.$linkmanager_plugin_main_file));
		echo $linkmanager_description;
		die(); // stop posting any other information
	}
}


/**
 * BASIC ALGORITM:
 */
function linksmanager_main(){
	global $linkmanager_url; // path to site: LinkManger system
	global $linkmanager_request; // result of plugin post request
	global $linkmanager_on; // indicator of linksmanager plugin state

	$cansend = true; // indicator of correct plugin settings

	$linkmanager_on = linksmanager_inside(); // we in linksmanager?

	if($linkmanager_on){
		//---------------Make PHP request------------------
		$content = ''; // content from LinkManager server

		// init $_GET and $_POST (arrays)
		if (get_magic_quotes_gpc()) {
			unstrip_array($_GET);
			unstrip_array($_POST);
		}

		// GET/POST transfer
		if(isset($_POST['GET']['PHPSESSID'])){
			$_POST['PHPSESSID'] = $_POST['GET']['PHPSESSID'];
		}
		if(isset($_POST['POST']['PHPSESSID'])){
			$_POST['PHPSESSID'] = $_POST['POST']['PHPSESSID'];
		}
		if(isset($_POST['PHPSESSID'])){
			$_GET['PHPSESSID'] = $_POST['PHPSESSID'];
		}

		session_name('PHPSESSID');
		if (isset($_GET['PHPSESSID'])) session_id($_GET['PHPSESSID']);
		session_start();

		if (get_option('lm_site_ID')>0) { // check if ID of site is set on
			$Site = get_option('lm_site_ID');
		}else{
			$cansend = false;
			$linkmanager_request = "Can't connect to Link Manager system. Make sure thats LinksManager plugin set correctly.";
		}

		$URL = $linkmanager_url.'index.php?section=links';
		$ContactURL = $linkmanager_url.'index.php?section=contact';
		// Set client url for absolute link
		$_POST['Site_URL'] = 'http://'.$_SERVER['HTTP_HOST'].(!isset($_GET['page_id'])?'/'.get_option('lm_url').'/':'');

		if(!isset($_POST['action'])){ // set initial values of 'action'
			if (isset($_GET) && count($_GET)>0){
				if(isset($_GET['action'])) {
					if($_GET['action'] != '') {
						$_POST['action'] = $_GET['action'];
					}else{
						$_GET['action'] = 'view';
						$_POST['action'] = 'view';
					}
				}

				if(isset($_GET['CatID'])){
					if($_GET['CatID'] != '') $_POST['CatID'] = $_GET['CatID'];
				}
			}else{
				if(!isset($_POST['action'])) $_POST['action'] = 'view';
			}
		}

		// Set Action and Category
		if (get_option('lm_permalnk') != 'Default') {
			$t_path = linksmanager_parse_URLPath();
			if (end($t_path) != get_option('lm_url')) $_POST['TagName'] = end($t_path);
			else $_POST['TagName'] = '';
		}

		$_POST['WP'] = 'true'; // That param tell API system: this is WP send you request
		$_POST['Permalnk'] = get_option('lm_permalnk'); // Send permalink flag

		if($cansend){
			$RequestData = array('XMLRequest' => true, 'Site' => $Site, 'GET' => $_GET, 'POST' => $_POST);
			$RequestData['GET']['PHPSESSID'] = session_id();
			$ResponseData = linksmanager_file_post_contents($URL, linksmanager_http_parse_query($RequestData));

			// Parse request result
			$Headers = array();

			//			ob_start();
			if( function_exists( 'domxml_open_mem' ) ){
				if ($dom = domxml_open_mem($ResponseData)){
					$root = $dom->document_element();
					foreach ($root->get_elements_by_tagname("header") as $Node){
						$Headers[] = $Node->get_content();
					}
					foreach ($root->get_elements_by_tagname("PageTitle") as $Node){
						$linkmanager_request["PageTitle"] = base64_decode($Node->get_content());
					}
					foreach ($root->get_elements_by_tagname("PageDescription") as $Node){
						$linkmanager_request["PageDescription"] = base64_decode($Node->get_content());
					}
					foreach ($root->get_elements_by_tagname("PageKeyWords") as $Node){
						$linkmanager_request["PageKeyWords"] = base64_decode($Node->get_content());
					}
					foreach ($root->get_elements_by_tagname("PageAutor") as $Node){
						$linkmanager_request["PageAutor"] = base64_decode($Node->get_content());
					}
					foreach ($root->get_elements_by_tagname("PageCSS") as $Node){
						$linkmanager_request["PageCSS"] = base64_decode($Node->get_content());
					}
					foreach ($root->get_elements_by_tagname("direct_output") as $Node){
						$linkmanager_request["direct_output"] = base64_decode($Node->get_content());
					}
				}
			} else {
				$objXml= new DOMDocument();
				if ($objXml->loadXML( $ResponseData )){
					foreach ($objXml->documentElement->getElementsByTagName("header") as $Node){
						$Headers[] = $Node->nodeValue;//$Node->nodeValue;//htmlspecialchars_decode($Node->nodeValue);//htmlspecialchars($Node->nodeValue);
					}
					foreach ($objXml->documentElement->getElementsByTagName("PageTitle") as $Node){
						$linkmanager_request["PageTitle"] = base64_decode($Node->nodeValue);
					}
					foreach ($objXml->documentElement->getElementsByTagName("PageDescription") as $Node){
						$linkmanager_request["PageDescription"] = base64_decode($Node->nodeValue);
					}
					foreach ($objXml->documentElement->getElementsByTagName("PageKeyWords") as $Node){
						$linkmanager_request["PageKeyWords"] = base64_decode($Node->nodeValue);
					}
					foreach ($objXml->documentElement->getElementsByTagName("PageAutor") as $Node){
						$linkmanager_request["PageAutor"] = base64_decode($Node->nodeValue);
					}
					foreach ($objXml->documentElement->getElementsByTagName("PageCSS") as $Node){
						$linkmanager_request["PageCSS"] = base64_decode($Node->nodeValue);
					}
					foreach ($objXml->documentElement->getElementsByTagName("direct_output") as $Node){
						$linkmanager_request["direct_output"] = base64_decode($Node->nodeValue);
					}
				}
			}
			//			ob_end_clean();

			if(count($Headers)>0){ // if LinksManager server sent headers... we sent headers too
				foreach ($Headers as $Header){
					header($Header);
				}
			}

			/*// if result not single page... then post it and stop
			 if((substr_count($linkmanager_request,'<head>') == 0)||(substr_count($linkmanager_request,'</head>') == 0)||(substr_count($linkmanager_request,'<body>') == 0)||(substr_count($linkmanager_request,'</body>') == 0)){
			 echo $linkmanager_request;
			 die();
			 }*/
		}
	}else{
		$linkmanager_on = false; // turn off linksmanager... if error or we into another WP page
	}
}

// post linksmanager page title
function linksmanager_set_title($title){
	global $linkmanager_request; // result of plugin post request
	global $linkmanager_on; // indicator of linksmanager plugin state
	global $aioseop_options; // all-in-one-seo-pack array of variables

	// if we into linksmanger
	if($linkmanager_on){
		if(isset($linkmanager_request["PageTitle"]))
		if(isset($aioseop_options['aiosp_page_title_format'])) $aioseop_options['aiosp_page_title_format'] = $linkmanager_request["PageTitle"];//.' | '.$aioseop_options['aiosp_page_title_format'];
		else $title = $linkmanager_request["PageTitle"];
	}

	return $title;
}

// post linksmanager page meta(keyword, discription, etc)
function linksmanager_set_meta(){
	global $linkmanager_request; // result of plugin post request
	global $linkmanager_on; // indicator of linksmanager plugin state
	global $aioseop_options; // all-in-one-seo-pack array of variables
	global $meta_outputed; // if Description and/or KeyWords was printed

	// if we into linksmanger
	if($linkmanager_on){
		if(isset($aioseop_options)) {
			if(isset($linkmanager_request["PageDescription"]) && !$meta_outputed) {
				add_filter('aioseop_description_override', 'linksmanager_get_description');
			}
			if(isset($linkmanager_request["PageKeyWords"]) && !$meta_outputed) {
				add_filter('aioseop_keywords', 'linksmanager_get_keywords');
			}
		} else {
			if(isset($linkmanager_request["PageDescription"])) echo $linkmanager_request["PageDescription"]."\n";
			if(isset($linkmanager_request["PageKeyWords"])) echo $linkmanager_request["PageKeyWords"]."\n";
			$meta_outputed = true;
		}
		
		if($meta_outputed) {
			if(isset($linkmanager_request["PageAutor"])) echo $linkmanager_request["PageAutor"]."\n";
			if(isset($linkmanager_request["PageCSS"])) echo $linkmanager_request["PageCSS"]."\n";
		} else {
			add_action('wp_head', 'linksmanager_set_meta'); // set meta
			$meta_outputed = true;
		}
	}
}

// get linksmanager meta description
function linksmanager_get_description($description = '') {
	global $linkmanager_request; // result of plugin post request
	$description .= ($description != '' && $description[strlen($description)-1] != '.'?'. ':'').linksmanager_parse_meta($linkmanager_request["PageDescription"]);
	return $description;
}

// get linksmanager meta keywords
function linksmanager_get_keywords($keywords = '') {
	global $linkmanager_request; // result of plugin post request
	$keywords .= ($keywords != ''?', ':'').linksmanager_parse_meta($linkmanager_request["PageKeyWords"]);
	return $keywords;
}

// parse keywords
function linksmanager_parse_meta($meta = ''){
	global $linkmanager_request; // result of plugin post request

	if(isset($meta)) {
		preg_match('/content=\"(.*)\" \/>/iU', $meta, $matches);
		if (isset($matches[1])) $meta = $matches[1];
		else $meta = '';
	}
	return $meta;
}

// synchronize with all-in-one-seo-pack canonical url
function linksmanager_add_canonical($url){
	global $linkmanager_on; // indicator of linksmanager plugin state
	
	// if we into linksmanger
	if($linkmanager_on){
		$linkmgr_path = '';
		if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI']!=='') $_SERVER['REDIRECT_URL'] = $_SERVER['REQUEST_URI'];
		$linkmgr_path = $_SERVER['REDIRECT_URL'];
		
		if ($linkmgr_path !=='') {
			$parsed_url = parse_url($url);
			$url .= str_replace($parsed_url['path'], '', $linkmgr_path);
		}
	}
	
	return $url;
}

// post linksmanager page content
function linksmanager_set_content($content){
	global $linkmanager_request; // result of plugin post request
	global $linkmanager_on; // indicator of linksmanager plugin state

	// if we into linksmanger
	if($linkmanager_on){
		if(isset($linkmanager_request["direct_output"])) $content = $linkmanager_request["direct_output"];
	}

	return $content;
}


/**
 * OPTIONS:
 *
 * Basic menu
 */
function get_admin_form(){
	global $linkmanager_url; // path to site: LinkManger system

	// save plugin options
	if (isset($_POST['lm_search'])&&!empty($_POST['lm_search'])) {

		if (isset($_POST['lm_login'])&&($_POST['lm_login'] != get_option('lm_login'))){
			update_option('lm_login', $_POST['lm_login']);
			echo '<div id="message" class="updated fade"><p><strong>Login Updated.</strong></p></div>';
		}

	} elseif (isset($_POST['lm_update'])&&!empty($_POST['lm_update'])) {

		if (isset($_POST['lm_url'])&&($_POST['lm_url'] != get_option('lm_url'))){
			update_option('lm_url', $_POST['lm_url']);
			echo '<div id="message" class="updated fade"><p><strong>URL Updated.</strong></p></div>';
		}

		if (isset($_POST['lm_site_ID'])&&($_POST['lm_site_ID'] != get_option('lm_site_ID'))){
			update_option('lm_site_ID', $_POST['lm_site_ID']);
			echo '<div id="message" class="updated fade"><p><strong>Site Updated.</strong></p></div>';
		}

		if (isset($_POST['lm_permalnk'])&&($_POST['lm_permalnk'] != get_option('lm_permalnk'))){
			update_option('lm_permalnk', $_POST['lm_permalnk']);
			echo '<div id="message" class="updated fade"><p><strong>Type of permalink Updated.</strong></p></div>';
		}

	} elseif (isset($_POST['lm_reset'])&&!empty($_POST['lm_reset'])) {
		// Reset all options to default
		update_option('lm_url', 'linksmanager'); // reset linksmanager page name option
		update_option('lm_login', ''); // reset Login name option
		update_option('lm_site_ID', 0); // reset site ID option
		update_option('lm_permalnk', 'Default'); // reset site ID option

		echo '<div id="message" class="updated fade"><p><strong>All options was reset to default.</strong></p></div>';
	}

	//---------------Make PHP request------------------
	$cansend = true; // indicator of correct plugin settings
	$success_request = true; // indicator of success request

	if(get_option('lm_login') != '') $_POST['lm_login'] = get_option('lm_login');

	if (!empty($_POST['lm_login'])) { // check if Login is set
		$Login = $_POST['lm_login'];
	}else{
		$cansend = false;
		$success_request = false;
	}

	if($cansend){
		$URL = $linkmanager_url.'index.php?section=links';
		$ContactURL = $linkmanager_url.'index.php?section=contact';

		$_GET['action'] = 'login'; // request to login
		$_POST['action'] = 'login';
		$_POST['WP'] = 'true';// Set that param... to tell API system: this is WP send you request

		$RequestData = array('XMLRequest' => true, 'Login' => $Login, 'Site' => '0', 'GET' => $_GET, 'POST' => $_POST);
		$ResponseData = linksmanager_file_post_contents($URL, linksmanager_http_parse_query($RequestData));

		// First parsing result of request
		//		ob_start();
		if( function_exists( 'domxml_open_mem' ) ){
			if ($dom = domxml_open_mem($ResponseData)){
				$root = $dom->document_element();
				foreach ($root->get_elements_by_tagname("direct_output") as $Node){
					$request = base64_decode($Node->get_content());
				}
			}
		} else {
			$objXml= new DOMDocument();
			if ($objXml->loadXML( $ResponseData )){
				foreach ($objXml->documentElement->getElementsByTagName("direct_output") as $Node){
					$request = base64_decode($Node->nodeValue);
				}
			}
		}
		//		ob_end_clean();
		
		if ($request !== 'Not found') {
			if ($request !== '') { 
				// parse request
				$linkmanager_sites = array();
				$linkmanager_counter = 0;
				while(linksmanager_parse($request, '<site-'.$linkmanager_counter.'>', '</site-'.$linkmanager_counter.'>') != ''){
					$t_site = linksmanager_parse($request, '<site-'.$linkmanager_counter.'>', '</site-'.$linkmanager_counter.'>');
					$linkmanager_sites[linksmanager_parse($t_site, '<ID>', '</ID>')] = linksmanager_parse($t_site, '<URL>', '</URL>');
					$linkmanager_counter++;
				}
			} else {
				echo '<div id="message" class="error fade"><p><strong>Site(s) not found. Please setup your site(s) on admin page of you LinkMGR account.</strong></p></div>';
			}
		} else {
			echo '<div id="message" class="error fade"><p><strong>ERROR: User with such login - not found or inactive.</strong></p></div>';
		}
	}


	echo '<div class="wrap">
		<h2>Link Manager</h2>
		<form id="second" name="second" method="post" action="">
			<label for="Login">LinkMgr login name:</label>
			<input id="Login" name="lm_login" type="text" value="'.get_option('lm_login').'"/>
			<input type="submit" id="lm_search" name="lm_search" value="Apply">
		</form><br />';
	echo '<form id="first" name="first" method="post" action="">';
	if(isset($linkmanager_sites)&&(count($linkmanager_sites)>0)){
		echo '<label for="SiteID">Site:</label>
			<select id="SiteID" name="lm_site_ID">';
		foreach($linkmanager_sites as $site_id => $site_url){
			echo '<option value="'.$site_id.'" '.(get_option('lm_site_ID') == $site_id?'selected':'').'>'.$site_url.'</option>';
		}
		echo '</select><br /><br />';
	}
	echo '<label for="URL">Page name (slug):</label>
			<input type="text" id="URL" name="lm_url" value="'.get_option('lm_url').'" /><br /><br />';
	// Permalink section
	echo '<h3>Permalink Settings for Link Pages:</h3>';
	echo '
	<input type="radio" name="lm_permalnk" value="Default" '.(get_option('lm_permalnk')=='Default'?'checked="checked"':'').'>Default<br>
	<input type="radio" name="lm_permalnk" value="TagName" '.(get_option('lm_permalnk')=='TagName'?'checked="checked"':'').'>TagName (/TagName/)<br>
	<input type="radio" name="lm_permalnk" value="FullTagName" '.(get_option('lm_permalnk')=='FullTagName'?'checked="checked"':'').'>Full path using TagName (/parents_TagName/TagName/)<br>
	';
	// Notice
	echo '<br /><b>Note:</b> You must insert "&lt;!-- linkmgrmapgen --&gt;" into the HTML of the page where you would like the LinkMgr site map to appear.<br /><br />';
	echo '<input type="submit" id="lm_update" name="lm_update" value="Save"></form>';
	// Reset to default
	echo '
		<form id="second" name="second" method="post" action="">
		<input type="submit" id="lm_reset" name="lm_reset" value="Reset all options">
		</form>';
	echo '</div>';
}


/**
 * Sub menu
 */
function linksmanager_plugin_actions($links, $file){
	static $this_plugin;

	if( !$this_plugin ) $this_plugin = plugin_basename(__FILE__);

	if( $file == $this_plugin )
	{
		$settings_link = '<a href="options-general.php?page=links-manager.php">' . __('Settings') . '</a>';
		$links = array_merge( array($settings_link), $links); // before other links
	}
	return $links;
}


/**
 * Init plugin menu
 */
function linksmanager_admin_menu(){
	add_options_page('Soundst LinkMgr Option', 'Soundst LinkMgr', 8, basename(__FILE__), 'get_admin_form');
	if ( current_user_can('edit_posts') && function_exists('add_submenu_page') ) {
		add_filter( 'plugin_action_links', 'linksmanager_plugin_actions', 10, 2 );
	}
}


/**
 * REGISTER FILTERS:
 */
register_activation_hook(__FILE__, "linksmanager_install"); // install plugin
register_deactivation_hook(__FILE__, "linksmanager_uninstall"); // uninstall plugin
add_action('admin_menu', 'linksmanager_update'); // update plugin
add_action('install_plugins_pre_plugin-information', 'linksmanager_update_info'); // post update info
add_action('admin_menu', 'linksmanager_admin_menu'); // plugin option

// Compatibility with all-in-one-seo-pack
if (isset($aioseop_options)) {
	add_filter('wp_head', 'linksmanager_main', 1); // plugin basic algoritm
	add_filter('wp_head', 'linksmanager_set_title', 2); // set title
	add_action('wp_head', 'linksmanager_set_meta', 2); // set meta
	// Add full linkmgr path to canonical url
	add_filter('aioseop_canonical_url', 'linksmanager_add_canonical');
} else {
	//remove_action('wp_head', 'rel_canonical');
	add_filter('single_post_title', 'linksmanager_main', 1); // plugin basic algoritm
	add_filter('single_post_title', 'linksmanager_set_title'); // set title
	add_action('wp_head', 'linksmanager_set_meta'); // set meta
}

add_filter('the_content', 'linksmanager_set_content', 11 ); // set content


/**
 * WIDGETS:
 *
 * LinksManager widget class.
 * This class handles everything that needs to be handled with the widget:
 * the settings, form, display, and update.
 */
class LinksManager_Category extends WP_Widget { // Widget for post category
	function LinksManager_Category(){
		parent::WP_Widget(false, $name = 'LinkMgr - Categories');
	}

	function widget($args, $instance){
		$linkmanager_widget_result = '';
		if ($instance['Format'] == 'enable') $instance['Format'] = 'default';
		else $instance['Format'] = 'namedesc1';

		$linkmanager_widget_result = linksmanager_widget_post_request("category", $instance);

		$div_class = (!empty($instance['div_class'])?$instance['div_class']:'linkmgr_widget_category'); // set widget class name

		extract( $args );
		echo '<div class="'.$div_class.'">';
		if(!empty($instance['title'])) if($instance['title']!=' ') echo '<div class="'.$div_class.'-title">'.$instance['title'].'</div>'; // output title
		if($linkmanager_widget_result) echo '<div class="'.$div_class.'-url">'.$linkmanager_widget_result.'</div>'; // output URL's
		if(!empty($instance['text'])) echo '<div class="'.$div_class.'-text">'.$instance['text'].'</div>'; // output description
		echo '</div>';
	}

	function update($new_instance, $old_instance){
		/*if($new_instance['title'] == '') {
			$Directories = linksmanager_widget_admin_menu_request(); // get all directories
			if ($Directories && count($Directories)>0) {
			foreach($Directories as $obj_cat){
			if($obj_cat['ID'] == $new_instance['dir']) $new_instance['title'] = $obj_cat['Name'];
			}
			}
			}*/

		return $new_instance;
	}

	function form($instance){
		// main widget options
		$NEP = esc_attr($instance['NEP']); // Show new enter point directories?
		$Shared = esc_attr($instance['Shared']); // Show shared directories?
		$display_root = esc_attr($instance['display_root']); // Display root of directories?
		$dir = esc_attr($instance['dir']); // Root directory
		$SubLevelSelector = esc_attr($instance['SubLevelSelector']); // State of sub categories selector
		$SubLevel = esc_attr($instance['SubLevel']); // Deep level of sub categories
		$Format =  esc_attr($instance['Format']); // Format of display
		// other widget options
		$title = esc_attr($instance['title']); // Title of widget
		$text = esc_attr($instance['text']); // Text of widget
		$div_class = esc_attr($instance['div_class']); // Class name of widget div

		$Directories = linksmanager_widget_admin_menu_request(); // get all directories
		
		echo '
		<p><label for="'.$this->get_field_id('title').'"> '._e('Title:').'</label>
		<input class="widefat" id="'.$this->get_field_id('title').'"
		name="'.$this->get_field_name('title').'" type="text"
		value="'.$title.'" /></p>'; // output "title" option
		echo '
		<p><label for="'.$this->get_field_id('text').'"> '._e('Text:').'</label>
		<input class="widefat" id="'.$this->get_field_id('text').'"
		name="'.$this->get_field_name('text').'" type="text"
		value="'.$text.'" /></p>'; // output "text" option
		echo '<p>Display Options:</p>';
		echo '<input type="checkbox" name="'.$this->get_field_name('NEP').'" value="enable" '.($NEP == 'enable'?' checked ':'').' /> New entry point<br />';
		echo '<input type="checkbox" name="'.$this->get_field_name('Shared').'" value="enable" '.($Shared == 'enable'?' checked ':'').' /> Include shared directories<br />';
		echo '<input type="checkbox" name="'.$this->get_field_name('display_root').'" value="enable" '.($display_root == 'enable'?' checked ':'').' /> Include selected category<br />';
		echo '<input type="checkbox" name="'.$this->get_field_name('Format').'" value="enable" '.($Format == 'enable'?' checked ':'').' /> Use short format<br /><br />';
		if ($Directories && count($Directories)>0) { // output "select directory" option
			echo '<p><label for="'.$this->get_field_id('dir').'"> '._e('Link Category:').'</label>';
			echo '<select class="widefat" id="'.$this->get_field_id('dir').'" name="'.$this->get_field_name('dir').'" >';
			//echo '<option value="" '.($dir == ''?'selected':'').'>-//-</option>';
			foreach($Directories as $obj_cat){
				echo '<option value="'.(get_option('lm_permalnk')=='Default'?$obj_cat['ID']:$obj_cat['TagName']).'" '.($obj_cat['ID']==$dir||$obj_cat['TagName']==$dir?'selected':'').'>';
				for ($i=0;$i<(int)$obj_cat['Level'];$i++) echo "&nbsp;&nbsp;&nbsp;";
				echo $obj_cat['Name'].'</option>';
			}
			echo '</select></p>';
		}

		// Deep level of sub categories
		echo '<p><label for="'.$this->get_field_id('SubLevelSelector').'"> '._e('Select display level of subdirectory:').'</label>';
		echo '
		<select class="widefat" id="'.$this->get_field_id('SubLevelSelector').'" name="'.$this->get_field_name('SubLevelSelector').'">
		<option value="0" '.($SubLevelSelector=='0'?'selected="selected"':'').'>All</option>
		<option value="-1" '.($SubLevelSelector=='-1'?'selected="selected"':'').'>None</option>
		<option value="custom" '.($SubLevelSelector=='custom'?'selected="selected"':'').'>Custom</option>
		</select></p>';
		echo '
		<div id="'.$this->get_field_id('SubLevel').'-LinkMgr_SubLeveldiv" style="display: '.($SubLevelSelector=='custom'?'block':'none').'">
		<p><label for="'.$this->get_field_id('SubLevel').'">Custom display level of subdirectory (only integer number):</label>
		<input class="widefat" id="'.$this->get_field_id('SubLevel').'"
		name="'.$this->get_field_name('SubLevel').'" type="text"
		value="'.$SubLevel.'" /></p></div>';
		echo '
		<script type="text/javascript">
		var selectmenu = document.getElementById("'.$this->get_field_id('SubLevelSelector').'");
		var div = document.getElementById("'.$this->get_field_id('SubLevel').'-LinkMgr_SubLeveldiv");
		var subLevel = document.getElementById("'.$this->get_field_id('SubLevel').'");
		
		selectmenu.onchange = function(){
			var chosenoption = this.options[this.selectedIndex];
			if (chosenoption.value == "custom"){
				div.style.display = "block";
				subLevel.value = "";
			}else{
				div.style.display = "none";
				subLevel.value = chosenoption.value;
			}
		}
		</script>';
		echo '<p>Other options:</p>';
		echo '
		<p><label for="'.$this->get_field_id('div_class').'"> '._e('CSS DIV class:').'</label>
		<input class="widefat" id="'.$this->get_field_id('div_class').'"
		name="'.$this->get_field_name('div_class').'" type="text"
		value="'.$div_class.'" /></p>'; // output "div class" option		
	}

}

class LinksManager_Links extends WP_Widget { // Widget for post links
	function LinksManager_Links(){
		parent::WP_Widget(false, $name = 'LinkMgr - Links');
	}

	function widget($args, $instance){
		$linkmanager_widget_result = '';

		if ($instance['Format'] == 'enable') $instance['Format'] = 'horkwd1';
		else $instance['Format'] = 'default';

		$linkmanager_widget_result = linksmanager_widget_post_request("links", $instance); // get all links

		$div_class = (!empty($instance['div_class'])?$instance['div_class']:'linkmgr_widget_links'); // set widget class name

		extract( $args );
		echo '<div class="'.$div_class.'">';
		if(!empty($instance['title'])) if($instance['title']!=' ') echo '<div class="'.$div_class.'-title">'.$instance['title'].'</div>'; // output title
		if($linkmanager_widget_result) echo '<div class="'.$div_class.'-url">'.$linkmanager_widget_result.'</div>'; // output URL's
		if(!empty($instance['text'])) echo '<div class="'.$div_class.'-text">'.$instance['text'].'</div>'; // output description
		echo '</div>';
	}

	function update($new_instance, $old_instance){
		/*if($new_instance['title'] == '') {
			$Directories = linksmanager_widget_admin_menu_request(); // get all directories
			if ($Directories && count($Directories)>0) {
			foreach($Directories as $obj_cat){
			if($obj_cat['ID'] == $new_instance['dir']) $new_instance['title'] = $obj_cat['Name'];
			}
			}
			}*/

		return $new_instance;
	}

	function form($instance){
		// main widget options
		$dir = esc_attr($instance['dir']);
		$Format =  esc_attr($instance['Format']); // Format of display
		// other widget options
		$title = esc_attr($instance['title']);
		$text = esc_attr($instance['text']);
		$div_class = esc_attr($instance['div_class']);

		$Directories = linksmanager_widget_admin_menu_request(); // get all directories

		//echo '<p>Main options:</p>';
		echo '
		<p><label for="'.$this->get_field_id('title').'"> '._e('Title:').'</label>
		<input class="widefat" id="'.$this->get_field_id('title').'"
		name="'.$this->get_field_name('title').'" type="text"
		value="'.$title.'" /></p>'; // output "title" option
		echo '
		<p><label for="'.$this->get_field_id('text').'"> '._e('Text:').'</label>
		<input class="widefat" id="'.$this->get_field_id('text').'"
		name="'.$this->get_field_name('text').'" type="text"
		value="'.$text.'" /></p>'; // output "text" option
		echo '<p><input type="checkbox" name="'.$this->get_field_name('Format').'" value="enable" '.($Format == 'enable'?' checked ':'').' /> Use short format.</p>';
		if ($Directories && count($Directories)>0) { // output "select directory" option
			echo '<p><label for="'.$this->get_field_id('dir').'"> '._e('Link Category:').'</label>';
			echo '<select class="widefat" id="'.$this->get_field_id('dir').'" name="'.$this->get_field_name('dir').'" >';
			//echo '<option value="" '.($dir == ''?'selected':'').'>-//-</option>';
			foreach($Directories as $obj_cat){
				//echo '<option value="'.$obj_cat['ID'].'" '.($obj_cat['ID'] == $dir?'selected':'').'>';
				echo '<option value="'.(get_option('lm_permalnk')=='Default'?$obj_cat['ID']:$obj_cat['TagName']).'" '.($obj_cat['ID']==$dir||$obj_cat['TagName']==$dir?'selected':'').'>';
				for ($i=0;$i<(int)$obj_cat['Level'];$i++) echo "&nbsp;&nbsp;&nbsp;";
				echo $obj_cat['Name'].'</option>';
			}
			echo '</select></p>';
		}
		echo '<p>Other options:</p>';
		echo '
		<p><label for="'.$this->get_field_id('div_class').'"> '._e('CSS DIV class:').'</label>
		<input class="widefat" id="'.$this->get_field_id('div_class').'"
		name="'.$this->get_field_name('div_class').'" type="text"
		value="'.$div_class.'" /></p>'; // output "div class" option

	}

}

class LinksManager_Linkinfo extends WP_Widget { // Widget for post link informations
	function LinksManager_Linkinfo(){
		parent::WP_Widget(false, $name = 'LinkMgr - Backlink');
	}

	function widget($args, $instance){
		$linkmanager_widget_result = '';
		$linkmanager_widget_result = linksmanager_widget_post_request("linkinfo", $instance); // get all links

		$div_class = (!empty($instance['div_class'])?$instance['div_class']:'linkmgr_widget_linkinfo'); // set widget class name

		extract( $args );
		echo '<div class="'.$div_class.'">';
		if(!empty($instance['title'])) if($instance['title']!=' ') echo '<div class="'.$div_class.'-title">'.$instance['title'].'</div>'; // output title
		if($linkmanager_widget_result) echo '<div class="'.$div_class.'-url">'.$linkmanager_widget_result.'</div>'; // output URL's
		if(!empty($instance['text'])) echo '<div class="'.$div_class.'-text">'.$instance['text'].'</div>'; // output description
		echo '</div>';
	}

	function update($new_instance, $old_instance){
		/*if($new_instance['title'] == '') {
			$Directories = linksmanager_widget_admin_menu_request(); // get all directories
			if ($Directories && count($Directories)>0) {
			foreach($Directories as $obj_cat){
			if($obj_cat['ID'] == $new_instance['dir']) $new_instance['title'] = $obj_cat['Name'];
			}
			}
			}*/

		return $new_instance;
	}

	function form($instance){
		// main widget options
		$dir = esc_attr($instance['dir']);
		// other widget options
		$title = esc_attr($instance['title']);
		$text = esc_attr($instance['text']);
		$div_class = esc_attr($instance['div_class']);

		$Directories = linksmanager_widget_admin_menu_request(); // get all directories

		//echo '<p>Main options:</p>';
		echo '
		<p><label for="'.$this->get_field_id('title').'"> '._e('Title:').'</label>
		<input class="widefat" id="'.$this->get_field_id('title').'"
		name="'.$this->get_field_name('title').'" type="text"
		value="'.$title.'" /></p>'; // output "title" option
		echo '
		<p><label for="'.$this->get_field_id('text').'"> '._e('Text:').'</label>
		<input class="widefat" id="'.$this->get_field_id('text').'"
		name="'.$this->get_field_name('text').'" type="text"
		value="'.$text.'" /></p>'; // output "text" option
		if ($Directories && count($Directories)>0) { // output "select directory" option
			echo '<p><label for="'.$this->get_field_id('dir').'"> '._e('Link Category:').'</label>';
			echo '<select class="widefat" id="'.$this->get_field_id('dir').'" name="'.$this->get_field_name('dir').'" >';
			//echo '<option value="" '.($dir == ''?'selected':'').'>-//-</option>';
			foreach($Directories as $obj_cat){
				//echo '<option value="'.$obj_cat['ID'].'" '.($obj_cat['ID'] == $dir?'selected':'').'>';
				echo '<option value="'.(get_option('lm_permalnk')=='Default'?$obj_cat['ID']:$obj_cat['TagName']).'" '.($obj_cat['ID']==$dir||$obj_cat['TagName']==$dir?'selected':'').'>';
				for ($i=0;$i<(int)$obj_cat['Level'];$i++) echo "&nbsp;&nbsp;&nbsp;";
				echo $obj_cat['Name'].'</option>';
			}
			echo '</select></p>';
		}
		echo '<p>Other options:</p>';
		echo '
		<p><label for="'.$this->get_field_id('div_class').'"> '._e('CSS DIV class:').'</label>
		<input class="widefat" id="'.$this->get_field_id('div_class').'"
		name="'.$this->get_field_name('div_class').'" type="text"
		value="'.$div_class.'" /></p>'; // output "div class" option

	}

}

class LinksManager_Submit extends WP_Widget { // Widget for submit link
	function LinksManager_Submit(){
		parent::WP_Widget(false, $name = 'LinkMgr - Submit');
	}

	function widget($args, $instance){
		$linkmanager_widget_result = '';
		$linkmanager_widget_result = linksmanager_widget_post_request("submit", $instance); // get all links

		$div_class = (!empty($instance['div_class'])?$instance['div_class']:'linkmgr_widget_submit'); // set widget class name

		extract( $args );
		echo '<div class="'.$div_class.'">';
		if(!empty($instance['title'])) if($instance['title']!=' ') echo '<div class="'.$div_class.'-title">'.$instance['title'].'</div>'; // output title
		if($linkmanager_widget_result) echo '<div class="'.$div_class.'-url">'.$linkmanager_widget_result.'</div>'; // output URL's
		if(!empty($instance['text'])) echo '<div class="'.$div_class.'-text">'.$instance['text'].'</div>'; // output description
		echo '</div>';
	}

	function update($new_instance, $old_instance){
		/*if($new_instance['title'] == '') {
			$Directories = linksmanager_widget_admin_menu_request(); // get all directories
			if ($Directories && count($Directories)>0) {
			foreach($Directories as $obj_cat){
			if($obj_cat['ID'] == $new_instance['dir']) $new_instance['title'] = $obj_cat['Name'];
			}
			}
			}*/

		return $new_instance;
	}

	function form($instance){
		// main widget options
		$dir = esc_attr($instance['dir']);
		// other widget options
		$title = esc_attr($instance['title']);
		$text = esc_attr($instance['text']);
		$div_class = esc_attr($instance['div_class']);

		$Directories = linksmanager_widget_admin_menu_request(); // get all directories

		//echo '<p>Main options:</p>';
		echo '
		<p><label for="'.$this->get_field_id('title').'"> '._e('Title:').'</label>
		<input class="widefat" id="'.$this->get_field_id('title').'"
		name="'.$this->get_field_name('title').'" type="text"
		value="'.$title.'" /></p>'; // output "title" option
		echo '
		<p><label for="'.$this->get_field_id('text').'"> '._e('Text:').'</label>
		<input class="widefat" id="'.$this->get_field_id('text').'"
		name="'.$this->get_field_name('text').'" type="text"
		value="'.$text.'" /></p>'; // output "text" option
		if ($Directories && count($Directories)>0) { // output "select directory" option
			echo '<p><label for="'.$this->get_field_id('dir').'"> '._e('Link Category:').'</label>';
			echo '<select class="widefat" id="'.$this->get_field_id('dir').'" name="'.$this->get_field_name('dir').'" >';
			//echo '<option value="" '.($dir == ''?'selected':'').'>-//-</option>';
			foreach($Directories as $obj_cat){
				//echo '<option value="'.$obj_cat['ID'].'" '.($obj_cat['ID'] == $dir?'selected':'').'>';
				echo '<option value="'.(get_option('lm_permalnk')=='Default'?$obj_cat['ID']:$obj_cat['TagName']).'" '.($obj_cat['ID']==$dir||$obj_cat['TagName']==$dir?'selected':'').'>';
				for ($i=0;$i<(int)$obj_cat['Level'];$i++) echo "&nbsp;&nbsp;&nbsp;";
				echo $obj_cat['Name'].'</option>';
			}
			echo '</select></p>';
		}
		echo '<p>Other options:</p>';
		echo '
		<p><label for="'.$this->get_field_id('div_class').'"> '._e('CSS DIV class:').'</label>
		<input class="widefat" id="'.$this->get_field_id('div_class').'"
		name="'.$this->get_field_name('div_class').'" type="text"
		value="'.$div_class.'" /></p>'; // output "div class" option

	}

}


/**
 * Register our widget.
 * 'LinksManager_Widget' is the widget class used below.
 */
function linksmanager_load_widgets(){
	register_widget( 'LinksManager_Category' );
	register_widget( 'LinksManager_Links' );
	//register_widget( 'LinksManager_Partners' ); // Partners
	register_widget( 'LinksManager_Linkinfo' ); // Linkinfo
	register_widget( 'LinksManager_Submit' ); // Submit
}


/**
 * Add function to widgets_init that load our widget.
 */
add_action('widgets_init', create_function('', 'return register_widget("LinksManager_Category");'));
add_action('widgets_init', create_function('', 'return register_widget("LinksManager_Links");'));
//add_action('widgets_init', create_function('', 'return register_widget("LinksManager_Partners");'));
add_action('widgets_init', create_function('', 'return register_widget("LinksManager_Linkinfo");'));
add_action('widgets_init', create_function('', 'return register_widget("LinksManager_Submit");'));


/**
 * Generate Site map
 */
function linksmanager_sitemap($content){
	global $linkmanager_url; // path to site: LinkManger system
	global $wpdb; // wordpress DB
	
	if (strpos($content, "<!-- linkmgrmapgen -->") !== false) {
		// Send request
		$cansend = true; // indicator of correct plugin settings
		$linkmanager_sitemap_request = ''; // rendered sitemap
	
		//---------------Make PHP request------------------
		if (get_option('lm_site_ID')>0) { // check if ID of site is set on
			$Site = get_option('lm_site_ID');
		}else{
			$cansend = false;
		}
	
		$URL = $linkmanager_url.'index.php?section=links';
	
		$t_GET['action'] = 'sitemap'; // set action for linkmgr
		$t_POST['action'] = $t_GET['action'];
	
		$t_POST['WP'] = 'true'; // That param tell API system: this is WP send you request
		$t_POST['Permalnk'] = get_option('lm_permalnk'); // Send permalink flag
		
		// Set client url for absolute link
		$t_POST['Site_URL'] = 'http://'.$_SERVER['HTTP_HOST'].(!isset($_GET['page_id'])?'/'.get_option('lm_url').'/':'');
		if(isset($_GET['page_id'])){
			$sql = "SELECT `ID` FROM `wp_posts` WHERE `post_name` = '".get_option('lm_url')."' and `post_type` = 'page' and post_status = 'publish'";
			$linksmanager_dbID = $wpdb->get_results($sql);
			$t_POST['page_id'] = $linksmanager_dbID[0]->ID;
		}
	
		if($cansend){
			$RequestData = array('XMLRequest' => true, 'Site' => $Site, 'GET' => $t_GET, 'POST' => $t_POST);
			$RequestData['GET']['PHPSESSID'] = session_id();
			$ResponseData = linksmanager_file_post_contents($URL, linksmanager_http_parse_query($RequestData));
			
			// Parse request result
			if( function_exists( 'domxml_open_mem' ) ){
				if ($dom = domxml_open_mem($ResponseData)){
					$root = $dom->document_element();
					foreach ($root->get_elements_by_tagname("Sitemap") as $Node){
						$linkmanager_sitemap_request = base64_decode($Node->get_content());
					}
				}
			}else{
				$objXml = new DOMDocument();
				if ($objXml->loadXML( $ResponseData )){
					foreach ($objXml->documentElement->getElementsByTagName("Sitemap") as $Node){
						$linkmanager_sitemap_request = base64_decode($Node->nodeValue);
					}
				}
			}
		}
		$content = str_replace('<!-- linkmgrmapgen -->', $linkmanager_sitemap_request, $content);
	}
	
	return $content;
}

add_filter('the_content', 'linksmanager_sitemap', 11);
?>
