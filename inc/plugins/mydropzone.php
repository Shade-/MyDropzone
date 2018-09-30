<?php
/**
 * MyDropzone
 * 
 * Replace MyBB attachments with a modern solution using AJAX.
 *
 * @package MyDropzone
 * @author  Shade <legend_k@live.it>
 * @license Copyrighted. This product is available to MyBBoost subscribers only.
 * @version 1.0
 */
 
if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if (!defined("PLUGINLIBRARY")) {
	define("PLUGINLIBRARY", MYBB_ROOT . "inc/plugins/pluginlibrary.php");
}

function mydropzone_info()
{
	return [
		'name'          =>  'MyDropzone',
		'description'   =>  'Replace MyBB attachments with a modern solution using AJAX.',
		'website'       =>  'http://www.mybboost.com',
		'author'        =>  'Shade',
		'version'       =>  '1.0',
		'compatibility' =>  '18*',
	];
}

function mydropzone_is_installed()
{
    global $cache;
    
	$info = mydropzone_info();
    $installed = $cache->read("shade_plugins");
    if ($installed[$info['name']]) {
        return true;
    }
    
}

function mydropzone_install()
{
	global $cache, $PL, $lang;
	
	if (!$lang->setting_group_mydropzone) {
		$lang->load('mydropzone');
	}
	
	if (!file_exists(PLUGINLIBRARY)) {
		flash_message($lang->mydropzone_pluginlibrary_missing, "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	$PL or require_once PLUGINLIBRARY;
	
	// Add settings
	$PL->settings('mydropzone', $lang->setting_group_mydropzone, $lang->setting_group_mydropzone_desc, [
		'use_imgur' => [
			'title' => $lang->setting_mydropzone_use_imgur,
			'description' => $lang->setting_mydropzone_use_imgur_desc,
			'value' => 0
		],
		'imgur_client_id' => [
			'title' => $lang->setting_mydropzone_imgur_client_id,
			'description' => $lang->setting_mydropzone_imgur_client_id_desc,
			'optionscode' => 'text',
			'value' => ''
		],
		'remove_confirmation' => [
			'title' => $lang->setting_mydropzone_remove_confirmation,
			'description' => $lang->setting_mydropzone_remove_confirmation_desc,
			'value' => 1
		]
	]);
	
	// Add stylesheets
	$PL->stylesheet('mydropzone.css', file_get_contents(dirname(__FILE__) . '/MyDropzone/stylesheets/mydropzone.css'));
	
	// Add templates	   
	$dir       = new DirectoryIterator(dirname(__FILE__) . '/MyDropzone/templates');
	$templates = array();
	foreach ($dir as $file) {
		if (!$file->isDot() and !$file->isDir() and pathinfo($file->getFilename(), PATHINFO_EXTENSION) == 'html') {
			$templates[$file->getBasename('.html')] = file_get_contents($file->getPathName());
		}
	}
	
	$PL->templates('mydropzone', 'MyDropzone', $templates);
	
	// Add the plugin to cache
    $info = mydropzone_info();
    $shade_plugins = $cache->read('shade_plugins');
    $shade_plugins[$info['name']] = [
        'title' => $info['name'],
        'version' => $info['version']
    ];
    $cache->update('shade_plugins', $shade_plugins);
}

function mydropzone_uninstall()
{
	global $cache, $PL;
	
	if (!file_exists(PLUGINLIBRARY)) {
		flash_message($lang->mydropzone_pluginlibrary_missing, "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	$PL or require_once PLUGINLIBRARY;
	
	$PL->settings_delete('mydropzone');
	$PL->stylesheet_delete('mydropzone');
	$PL->templates_delete('mydropzone');
	
	// Remove the plugin from cache
	$info = mydropzone_info();
    $shade_plugins = $cache->read('shade_plugins');
    unset($shade_plugins[$info['name']]);
    $cache->update('shade_plugins', $shade_plugins);
}

// Alter $templates->cache()
$plugins->add_hook('global_start', 'mydropzone_alter_templates_object');

// New thread
$plugins->add_hook('newthread_do_newthread_start', 'mydropzone_handle_attachments');
$plugins->add_hook('newthread_start', 'mydropzone_handle_attachments');

// Edit post
$plugins->add_hook('editpost_do_editpost_start', 'mydropzone_handle_attachments');
$plugins->add_hook('editpost_action_start', 'mydropzone_handle_attachments');

// New reply
$plugins->add_hook('newreply_do_newreply_start', 'mydropzone_handle_attachments');
$plugins->add_hook('newreply_start', 'mydropzone_handle_attachments');

// Quick reply
$plugins->add_hook('showthread_start', 'mydropzone_quickreply');

// Peekers in settings
if (defined('IN_ADMINCP')) {
	$plugins->add_hook("admin_page_output_footer", "mydropzone_settings_footer");
	$plugins->add_hook("admin_load", "mydropzone_ad");
}

// Advertising
function mydropzone_ad()
{
	global $cache, $mybb;
	
	$plugins = $cache->read('shade_plugins');
	if (!in_array($mybb->user['uid'], (array) $plugins['MyDropzone']['ad_shown'])) {
		
		flash_message('Thank you for using MyDropzone! You might also be interested in other great plugins on <a href="http://projectxmybb.altervista.org">MyBBoost</a>, where you can also get support for MyDropzone itself. If you have obtained this copy without subscribing to MyBBoost, please report this to @Shade. I am a med student and this plugin plays a big part in my income, which I use to pay taxes and books. Thank you.<br /><small>This message will not be shown again to you.</small>', 'success');
		
		$plugins['MyDropzone']['ad_shown'][] = $mybb->user['uid'];
		$cache->update('shade_plugins', $plugins);
		
	}
	
}

function mydropzone_alter_templates_object()
{
	global $templates;
	
	control_object($templates, '
function cache($templates)
{
	$names = explode(",", $templates);
	
	$find = array(
		"post_attachments_new",
		"post_attachments_add",
		"post_attachments_attachment"
	);
	
	$replacements = array(
		"mydropzone_attachments_new",
		"mydropzone_attachments_add",
		"mydropzone_attachments_attachment"
	);
	
	if (count(array_diff($find, $names)) != 2) {
		$names = array_merge(array_diff($names, $find), $replacements);
	}
	
	return parent::cache(implode(",", $names));
}

function get($title, $eslashes=1, $htmlcomments=1)
{
	$templates_to_replace = array(
		"post_attachments_new" => "mydropzone_attachments_new",
		"post_attachments_add" => "mydropzone_attachments_add",
		"post_attachments_attachment" => "mydropzone_attachments_attachment"
	);
	
	$find = array_keys($templates_to_replace);
	
	if (in_array($title, $find)) {
		$title = str_replace($title, $templates_to_replace[$title], $title);
	}
	
	return parent::get($title, $eslashes, $htmlcomments);
}

');
	
}

function mydropzone_handle_attachments()
{
	global $mybb, $attachedfile, $lang;
	
	$lang->load('mydropzone');

	if (!$mybb->get_input('dropzone', MyBB::INPUT_INT) or $mybb->request_method != 'post') {
	    return false;
	}
	
	// Handle errors
	if ($attachedfile['error']) {
		http_response_code(403);
		echo $attachedfile['error'];
		exit;
	}
	
	echo $attachedfile['aid'];
	    
	exit;
	
}

function mydropzone_quickreply()
{
	global $mybb, $forumpermissions, $pid, $db, $templates, $lang, $attachbox, $theme;
	
	if ($mybb->input['action'] != 'thread') {
		return false;
	}
	
	$lang->load('mydropzone');
	
	// --- Partial copy of newreply.php, lines 1140-1215 ---
	
	// Get a listing of the current attachments.
	if ($mybb->settings['enableattachments'] != 0 and $forumpermissions['canpostattachments'] != 0)
	{
		$noshowattach = '';
		$query = $db->simple_select("attachments", "SUM(filesize) AS ausage", "uid='".$mybb->user['uid']."'");
		$usage = $db->fetch_array($query);

		if ($usage['ausage'] > ($mybb->usergroup['attachquota']*1024) and $mybb->usergroup['attachquota'] != 0)
		{
			$noshowattach = 1;
		}

		if ($mybb->usergroup['attachquota'] == 0)
		{
			$friendlyquota = $lang->unlimited;
		}
		else
		{
			$friendlyquota = get_friendly_size($mybb->usergroup['attachquota']*1024);
		}

		$friendlyusage = get_friendly_size($usage['ausage']);
		$lang->attach_quota = $lang->sprintf($lang->attach_quota, $friendlyusage, $friendlyquota);

		if ($mybb->settings['maxattachments'] == 0 or ($mybb->settings['maxattachments'] != 0 and $attachcount < $mybb->settings['maxattachments']) and !$noshowattach)
		{
			eval("\$attach_add_options = \"".$templates->get("post_attachments_add")."\";");
		}

		if (($mybb->usergroup['caneditattachments'] or $forumpermissions['caneditattachments']) and $attachcount > 0)
		{
			eval("\$attach_update_options = \"".$templates->get("post_attachments_update")."\";");
		}

		if ($attach_add_options or $attach_update_options)
		{
			eval("\$newattach = \"".$templates->get("post_attachments_new")."\";");
		}

		eval("\$attachbox = \"".$templates->get("post_attachments")."\";");
	}
	
}

function mydropzone_settings_footer()
{
	global $mybb, $db;
	
	if ($mybb->input["action"] == "change" and $mybb->request_method != "post") {
	
		$gid = mydropzone_settings_gid();
		
		if ($mybb->input["gid"] == $gid or !$mybb->input['gid']) {
			
			echo '<script type="text/javascript">
	$(document).ready(function() {
		
		new Peeker($(".setting_mydropzone_use_imgur"), $("#row_setting_mydropzone_imgur_client_id"), /1/, true);
		
		add_star("row_setting_mydropzone_imgur_client_id");
		
	});
</script>';

		}
	}
}

function mydropzone_settings_gid()
{
	global $db;
	
	$query = $db->simple_select("settinggroups", "gid", "name = 'mydropzone'", array(
		"limit" => 1
	));
	$gid = (int) $db->fetch_field($query, "gid");
	
	return $gid;
}

// ZiNgA BuRgA's control_object
if(!function_exists('control_object')) {
	function control_object(&$obj, $code) {
		static $cnt = 0;
		$newname = '_objcont_'.(++$cnt);
		$objserial = serialize($obj);
		$classname = get_class($obj);
		$checkstr = 'O:'.strlen($classname).':"'.$classname.'":';
		$checkstr_len = strlen($checkstr);
		if(substr($objserial, 0, $checkstr_len) == $checkstr) {
			$vars = array();
			// grab resources/object etc, stripping scope info from keys
			foreach((array)$obj as $k => $v) {
				if($p = strrpos($k, "\0"))
					$k = substr($k, $p+1);
				$vars[$k] = $v;
			}
			if(!empty($vars))
				$code .= '
					function ___setvars(&$a) {
						foreach($a as $k => &$v)
							$this->$k = $v;
					}
				';
			eval('class '.$newname.' extends '.$classname.' {'.$code.'}');
			$obj = unserialize('O:'.strlen($newname).':"'.$newname.'":'.substr($objserial, $checkstr_len));
			if(!empty($vars))
				$obj->___setvars($vars);
		}
		// else not a valid object or PHP serialize has changed
	}
}