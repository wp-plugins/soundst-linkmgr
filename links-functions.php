<?php
/*
 ** The post request function.
 */
function linksmanager_file_post_contents($url,$post_data) {
	$p_url = parse_url($url);
	if (!isset($p_url['port'])) {
		if ($p_url['scheme'] == 'http') { $p_url['port']=80; }
		elseif ($p_url['scheme'] == 'https') { $p_url['port']=443; }
	}
	$p_url['query']=isset($p_url['query'])?$p_url['query']:'';

	$t_Request = 'http'.(isset($_SERVER['HTTPS'])?(($_SERVER['HTTPS']=='on')?'s':''):'').'://'.(isset($_SERVER['SERVER_NAME'])?$_SERVER['SERVER_NAME']:'').(isset($_SERVER['SCRIPT_NAME'])?$_SERVER['SCRIPT_NAME']:'').'?'.(isset($_SERVER['QUERY_STRING'])?$_SERVER['QUERY_STRING']:'');

	$p_url['protocol']=$p_url['scheme'].'://';
	$eol="\r\n";
	$headers =  "POST ".$url." HTTP/1.0".$eol.
		"Host: ".$p_url['host'].$eol. 
		"Referer: ".$t_Request.$eol.
		"User-Agent: LinkMgr Frontend".$eol.
		"Content-Type: application/x-www-form-urlencoded".$eol.
		"Content-Length: ".strlen($post_data).$eol.
	$eol.$post_data;
	$fp = fsockopen($p_url['host'], $p_url['port'], $errno, $errstr, 30);
	if($fp) {
		fputs($fp, $headers);
		$result = '';
		while(!feof($fp)) {$result .= fread($fp, 8192);}
		fclose($fp);
		//removes headers
		$pattern="/^.*?\r\n\r\n/s";
		$result=preg_replace($pattern,'',$result);
		return $result;
	}
}

function linksmanager_http_parse_query( $array = NULL, $convention = '%s' ){
	if( count( $array ) == 0 ){
		return '';
	} else {
		if( function_exists( 'http_build_query' ) ){
			$query = http_build_query( $array );
		} else {
			$query = '';
			foreach( $array as $key => $value ){
				if( is_array( $value ) ){
					$new_convention = sprintf( $convention, $key ) . '[%s]';
					$query .= linksmanager_http_parse_query( $value, $new_convention );
				} else {
					$key = urlencode( $key );
					$value = urlencode( $value );
					$query .= sprintf( $convention, $key ) . "=$value&";
				}
			}
		}
		return $query;
	}
}

function linksmanager_widget_post_request ($widgets_type, $instance = array()){
	global $linkmanager_url; // path to site: LinkManger system
	global $wpdb; // wordpress DB

	$cansend = true; // indicator of correct plugin settings
	$linkmanager_widget_request = '';

	//---------------Make PHP request------------------

	if (get_option('lm_site_ID')>0) { // check if ID of site is set on
		$Site = get_option('lm_site_ID');
	}else{
		$cansend = false;
	}

	$URL = $linkmanager_url.'index.php?section=links';
	$ContactURL = $linkmanager_url.'index.php?section=contact';

	//---------------Set GET and POST arrays------------------
	$t_GET = $_GET;
	$t_POST = $_POST;

	if (get_option('lm_permalnk')=='Default') {//get_option('lm_permalnk');
		$t_GET['CatID'] = (isset($instance['dir'])?$instance['dir']:''); // $root_dir
		$t_POST['CatID'] = $t_GET['CatID'];
	} else {
		$t_GET['TagName'] = (isset($instance['dir'])?$instance['dir']:'links'); // $root_dir
		$t_POST['TagName'] = $t_GET['TagName'];
	}
	
	$t_GET['action'] = 'widgets'; // set action for linkmgr
	$t_POST['action'] = $t_GET['action'];

	$t_POST['WP'] = 'true'; // That param tell API system: this is WP send you request
	$t_POST['widgets_type'] = $widgets_type;
	$t_POST['Permalnk'] = get_option('lm_permalnk'); // Send permalink flag

	$t_POST['NEP'] = (isset($instance['NEP'])?$instance['NEP']:'');
	$t_POST['Shared'] = (isset($instance['Shared'])?$instance['Shared']:'');
	$t_POST['display_root'] = (isset($instance['display_root'])?$instance['display_root']:'');
	$t_POST['Format'] = (isset($instance['Format'])?$instance['Format']:'default');

	if(isset($instance['SubLevel'])){
		if($instance['SubLevel'] === '-1') $t_POST['SubLevel'] = '0';
		elseif((int)$instance['SubLevel'] === 0) $t_POST['SubLevel'] = '';
		else $t_POST['SubLevel'] = (int)$instance['SubLevel'];
	}else{
		$t_POST['SubLevel'] = '';
	}

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
				foreach ($root->get_elements_by_tagname($widgets_type) as $Node){
					$linkmanager_widget_request = base64_decode($Node->nodeValue);
				}
			}
		}else{
			$objXml= new DOMDocument();
			if ($objXml->loadXML( $ResponseData )){
				foreach ($objXml->documentElement->getElementsByTagName($widgets_type) as $Node){
					$linkmanager_widget_request = base64_decode($Node->nodeValue);
				}
			}
		}
	}

	if($linkmanager_widget_request != '') return $linkmanager_widget_request;
	else return false;
}

function linksmanager_widget_admin_menu_request($action = 'give_all_directories'){
	global $linkmanager_url; // path to site: LinkManger system

	$cansend = true; // indicator of correct plugin settings
	$linkmanager_widget_request = '';

	//---------------Make PHP request------------------
	if (get_option('lm_site_ID')>0) { // check if ID of site is set on
		$Site = get_option('lm_site_ID');
	}else{
		$cansend = false;
	}

	$URL = $linkmanager_url.'index.php?section=links';
	$ContactURL = $linkmanager_url.'index.php?section=contact';

	//---------------Set GET and POST arrays------------------
	$t_GET = $_GET;
	$t_POST = $_POST;

	$t_GET['CatID'] = '';
	$t_POST['CatID'] = $t_GET['CatID'];
	$t_GET['action'] = $action; // set action for linkmgr
	$t_POST['action'] = $t_GET['action'];

	$t_POST['WP'] = 'true'; // That param tell API system: this is WP send you request

	if($cansend){
		$RequestData = array('XMLRequest' => true, 'Site' => $Site, 'GET' => $t_GET, 'POST' => $t_POST);
		$RequestData['GET']['PHPSESSID'] = session_id();
		$ResponseData = linksmanager_file_post_contents($URL, linksmanager_http_parse_query($RequestData));

		// Parse request result
		if( function_exists( 'domxml_open_mem' ) ){
			if ($dom = domxml_open_mem($ResponseData)){
				$root = $dom->document_element();
				foreach ($root->get_elements_by_tagname("dir_menu") as $Node){
					$linkmanager_widget_request = unserialize(base64_decode($Node->nodeValue));
				}
			}
		}else{
			$objXml= new DOMDocument();
			if ($objXml->loadXML( $ResponseData )){
				foreach ($objXml->documentElement->getElementsByTagName("dir_menu") as $Node){
					$linkmanager_widget_request = unserialize(base64_decode($Node->nodeValue));
				}
			}
		}
	}

	if($linkmanager_widget_request != '') return $linkmanager_widget_request;
	else return false;
}

/*
 ** Parsing function.
 */
function linksmanager_parse($string, $open, $close){
	$result = substr_replace($string, '', 0, (strripos($string, $open)+strlen($open)));
	$result = substr_replace($result, '', strripos($result, $close));
	return $result;
}

function linksmanager_parse_delete_tag($string, $open, $close){
	$part_to_dalate = stristr($string, $close, true);
	$part_to_dalate = substr_replace($part_to_dalate, '', 0, (strripos($part_to_dalate, $open)+strlen($open)));
	$result = substr_replace($string, '', strripos($string, $open), (strlen($open) + strlen($part_to_dalate) + strlen($close)));
	return $result;
}

function linksmanager_wp_version_validate($version){
	$this_version =	explode(".", $version);
	$pass_version = explode(".", '2.9.0');

	if($this_version[0] > $pass_version[0]) {
		return true;
	}elseif($this_version[1] > $pass_version[1] && $this_version[0] == $pass_version[0]){
		return true;
	}elseif($this_version[2] > $pass_version[2] && $this_version[1] == $pass_version[1] && $this_version[0] == $pass_version[0]){
		return true;
	}else{
		return false;
	}
}

/*
 * Helper function
 */
function linksmanager_inside($server_url = ''){
	global $wpdb; // wordpress DB

	// parsing current WP site url to deside: we in linksmanager?
	$curent_URL = linksmanager_parse_URLPath($server_url);
	//$curent_URL['path'] = str_ireplace("/", '', $curent_URL['path']);

	if(get_option('permalink_structure') != ''){
		if($curent_URL[0] == get_option('lm_url')) return true; // we in linksmanager
	}elseif(isset($_GET['page_id'])){
		$sql = "SELECT `ID` FROM `wp_posts` WHERE `post_name` = '".get_option('lm_url')."' and `post_type` = 'page' and post_status = 'publish'";
		$linksmanager_dbID = $wpdb->get_results($sql);
		if($_GET['page_id'] == $linksmanager_dbID[0]->ID) return true; // we in linksmanager
	}else{
		return false; // we out linksmanager
	}
}

function linksmanager_parse_URLPath($url = ''){
	// parsing current WP site url
	if ($url!=='') $curent_URL = $url;
	else $curent_URL = parse_url($_SERVER['REQUEST_URI']);
	
	if($curent_URL['path'][0] == '/') $curent_URL['path'] = substr($curent_URL['path'], 1);
	if($curent_URL['path'][strlen($curent_URL['path'])-1] == '/') $curent_URL['path'] = substr($curent_URL['path'], 0, -1);
	return explode("/", $curent_URL['path']);
}

/*
 * Special function
 */
// Functon for get some parts of curent plugin (Links_Manager):
// <linkmgr typ="category" ctg="nnn" fmt="default" depth="d" parent="y/n">
function linksmanager_plugin_part($type = '', $instance = array()){
	// Description of function paramiters
	//  $type; // typ (one of $widgets array) | required
	// Description of $instance - array | all array variables - optional
	//	$instance['dir']; // ctg (id)
	//	$instance['NEP']; // new enter point ("enable", "")
	//	$instance['Shared']; // shared categories ("enable", "")
	//  $instance['display_root']; // parent ("enable", "")
	//  $instance['SubLevel']; // depth (0 = All, -1 = None, (int) = next levels)
	//  $instance['Format']; // fmt ('default', 'namedesc1') - for categories; fmt ('default', 'horkwd1') - for links;
	// Design variables
	//	$instance['div_class']; // design class
	//	$instance['title']; // title
	//	$instance['text']; // some descriptive text
	
	$widgets = array('category', 'links', 'linkinfo', 'submit');
	
	if (in_array($type, $widgets)) {
		$linkmanager_widget_result = '';
		$linkmanager_widget_result = linksmanager_widget_post_request($type, $instance);

		$div_class = (!empty($instance['div_class'])?$instance['div_class']:'linkmgr_plugin_part'); // set widget class name

		$output = '';
		$output = '<div class="'.$div_class.'">';
		if(!empty($instance['title'])) if($instance['title']!=' ') $output .= '<div class="'.$div_class.'-title">'.$instance['title'].'</div>'; // output title
		if($linkmanager_widget_result) $output .= '<div class="'.$div_class.'-url">'.$linkmanager_widget_result.'</div>'; // output URL's
		if(!empty($instance['text'])) $output .= '<div class="'.$div_class.'-text">'.$instance['text'].'</div>'; // output description
		$output .= '</div>';

		return $output;
	}else{
		return false;
	}
}
?>
