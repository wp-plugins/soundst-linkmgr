<?php
/*
Description: Allow Links Manager run inside WP.
Author: Petr Voinov
Version: 1.1
Author URI: http://www.soundst.com/
*/

add_filter('rewrite_rules_array', 'wp_insertLMRewriteRules');
add_filter('query_vars', 'wp_insertLMRewriteQueryVars');
add_filter('init', 'flushLMRules', 9999);

// Remember to flush_rules() when adding rules
function flushLMRules(){
	global $wp_rewrite;
   	$wp_rewrite->flush_rules();
}

// Adding a new rule
function wp_insertLMRewriteRules($rules)
{
	$LM_page = get_option('lm_url');
	if ($LM_page===false || empty($LM_page)) $LM_page = 'linksmanager';
	
	$newrules = array();
	$newrules['^'.$LM_page.'/(.*)$'] = 'index.php?pagename='.$LM_page.'&route=$matches[1]';
	
	return $newrules + $rules;
}

// Adding the 'route' var so that WP recognizes it
function wp_insertLMRewriteQueryVars($vars)
{
	array_push($vars, 'route');
	return $vars;
}
?>