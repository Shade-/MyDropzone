<?php
/**
 * MyDropzone
 * 
 * Replace MyBB attachments with a modern solution using AJAX.
 *
 * @package MyDropzone
 * @author  Shade <shad3-@outlook.com>
 * @license Copyrighted
 * @version 1.1
 */

if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if (!defined("PLUGINLIBRARY")) {
	define("PLUGINLIBRARY", MYBB_ROOT . "inc/plugins/pluginlibrary.php");
}

function mydropzone_info()
{
	global $mybb;

	mydropzone_plugin_edit();

	if (mydropzone_is_installed() and $mybb->version_code > 1818) {

		global $PL;

		$PL or require_once PLUGINLIBRARY;

		if (mydropzone_apply_attachment_edits() !== true) {
			$apply = $PL->url_append('index.php',
				[
					'module' => 'config-plugins',
					'mydropzone' => 'apply',
					'my_post_key' => $mybb->post_code,
				]
			);
	        $description = "<br><br>Core edits missing. <a href='{$apply}'>Apply core edits.</a>";
		}
		else {
			$revert = $PL->url_append('index.php',
				[
					'module' => 'config-plugins',
					'mydropzone' => 'revert',
					'my_post_key' => $mybb->post_code,
				]
			);
			$description = "<br><br>Core edits in place. <a href='{$revert}'>Revert core edits.</a>";
		}

	}

	return [
		'name'          =>  'MyDropzone',
		'description'   =>  'Replace MyBB attachments with a modern solution using AJAX.' . $description,
		'website'       =>  'https://www.mybboost.com',
		'author'        =>  'Shade',
		'version'       =>  '1.1',
		'compatibility' =>  '18*',
	];
}

function mydropzone_is_installed()
{
    global $cache;

    $installed = $cache->read("shade_plugins");
    if ($installed['MyDropzone']) {
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
	$templates = [];
	foreach ($dir as $file) {
		if (!$file->isDot() and !$file->isDir() and pathinfo($file->getFilename(), PATHINFO_EXTENSION) == 'html') {
			$templates[$file->getBasename('.html')] = file_get_contents($file->getPathName());
		}
	}

	$PL->templates('mydropzone', 'MyDropzone', $templates);

	mydropzone_apply_attachment_edits(true);

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

	mydropzone_revert_attachment_edits(true);

	$PL->settings_delete('mydropzone');
	$PL->stylesheet_delete('mydropzone');
	$PL->templates_delete('mydropzone');

	// Remove the plugin from cache
	$info = mydropzone_info();
    $shade_plugins = $cache->read('shade_plugins');
    unset($shade_plugins[$info['name']]);
    $cache->update('shade_plugins', $shade_plugins);
}

function mydropzone_plugin_edit()
{
    global $mybb;

    if ($mybb->input['my_post_key'] == $mybb->post_code) {

        if ($mybb->input['mydropzone'] == 'apply') {
            if (mydropzone_apply_attachment_edits(true) === true) {
                flash_message('Successfully applied core edits.', 'success');
                admin_redirect('index.php?module=config-plugins');
            }
            else {
                flash_message('There was an error applying core edits.', 'error');
                admin_redirect('index.php?module=config-plugins');
            }

        }

        if ($mybb->input['mydropzone'] == 'revert') {

            if (mydropzone_revert_attachment_edits(true) === true) {
                flash_message('Successfully reverted core edits.', 'success');
                admin_redirect('index.php?module=config-plugins');
            }
            else {
                flash_message('There was an error reverting core edits.', 'error');
                admin_redirect('index.php?module=config-plugins');
            }

        }

    }
}

function mydropzone_apply_attachment_edits($apply = false)
{
	global $PL, $mybb;

	$PL or require_once PLUGINLIBRARY;

	$edits = [
		[
			'search' => [
				'if(!empty($attachedfile[\'error\']))',
				'{',
					'$ret[\'errors\'][] = $attachedfile[\'error\'];',
					'$mybb->input[\'action\'] = $action;',
				'}'
			],
			'before' => [
				'$args = [\'attachedfile\' => $attachedfile];',
				'$GLOBALS[\'plugins\']->run_hooks(\'mydropzone_core_add_attachment\', $args);'
			]
		]
	];

	$result = $PL->edit_core('mydropzone', 'inc/functions_upload.php', $edits, $apply);

	if ($result !== true) {
		$errors[] = $result;
	}

	if (count($errors) >= 1) {
		return $errors;
	}
	else {
		return true;
	}
}

function mydropzone_revert_attachment_edits($apply = false)
{
	global $PL;

	$PL or require_once PLUGINLIBRARY;

	return $PL->edit_core('mydropzone', 'inc/functions_upload.php', [], $apply);
}

global $mybb;

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

// MyBB 1.8.19
if ($mybb->version_code > 1818) {
	$plugins->add_hook('mydropzone_core_add_attachment', 'mydropzone_handle_attachments');
}

// Quick reply
$plugins->add_hook('showthread_start', 'mydropzone_quickreply');

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

		flash_message('Thank you for using MyDropzone! You might also be interested in other great plugins on <a href="https://www.mybboost.com">MyBBoost</a>, where you can also get support for MyDropzone itself.<br /><small>This message will not be shown again to you.</small>', 'success');

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

	$find = [
		"post_attachments_new",
		"post_attachments_add",
		"post_attachments_attachment"
	];

	$replacements = [
		"mydropzone_attachments_new",
		"mydropzone_attachments_add",
		"mydropzone_attachments_attachment"
	];

	if (count(array_diff($find, $names)) != 2) {
		$names = array_merge(array_diff($names, $find), $replacements);
	}

	return parent::cache(implode(",", $names));
}

function get($title, $eslashes=1, $htmlcomments=1)
{
	$templates_to_replace = [
		"post_attachments_new" => "mydropzone_attachments_new",
		"post_attachments_add" => "mydropzone_attachments_add",
		"post_attachments_attachment" => "mydropzone_attachments_attachment"
	];

	$find = array_keys($templates_to_replace);

	if (in_array($title, $find)) {
		$title = str_replace($title, $templates_to_replace[$title], $title);
	}

	return parent::get($title, $eslashes, $htmlcomments);
}

');

}

function mydropzone_handle_attachments(&$args)
{
	global $mybb, $attachedfile, $lang;

	if (!$attachedfile and $args) {
		$attachedfile = $args['attachedfile'];
	}

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

	// Copy of newreply.php, lines 1045-1130 @ MyBB 1.8.19
	// Get a listing of the current attachments.
	if($mybb->settings['enableattachments'] != 0 && $forumpermissions['canpostattachments'] != 0)
	{
		$attachcount = 0;
		if($pid)
		{
			$attachwhere = "pid='$pid'";
		}
		else
		{
			$attachwhere = "posthash='".$db->escape_string($posthash)."'";
		}
		$attachments = '';
		$query = $db->simple_select("attachments", "*", $attachwhere);
		while($attachment = $db->fetch_array($query))
		{
			$attachment['size'] = get_friendly_size($attachment['filesize']);
			$attachment['icon'] = get_attachment_icon(get_extension($attachment['filename']));
			$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
			if($mybb->settings['bbcodeinserter'] != 0 && $forum['allowmycode'] != 0 && (!$mybb->user['uid'] || $mybb->user['showcodebuttons'] != 0))
			{
				eval("\$postinsert = \"".$templates->get("post_attachments_attachment_postinsert")."\";");
			}
			$attach_mod_options = '';
			eval("\$attach_rem_options = \"".$templates->get("post_attachments_attachment_remove")."\";");
			if($attachment['visible'] != 1)
			{
				eval("\$attachments .= \"".$templates->get("post_attachments_attachment_unapproved")."\";");
			}
			else
			{
				eval("\$attachments .= \"".$templates->get("post_attachments_attachment")."\";");
			}
			$attachcount++;
		}
		$noshowattach = '';
		$query = $db->simple_select("attachments", "SUM(filesize) AS ausage", "uid='".$mybb->user['uid']."'");
		$usage = $db->fetch_array($query);
		if($usage['ausage'] > ($mybb->usergroup['attachquota']*1024) && $mybb->usergroup['attachquota'] != 0)
		{
			$noshowattach = 1;
		}
		if($mybb->usergroup['attachquota'] == 0)
		{
			$friendlyquota = $lang->unlimited;
		}
		else
		{
			$friendlyquota = get_friendly_size($mybb->usergroup['attachquota']*1024);
		}
		$lang->attach_quota = $lang->sprintf($lang->attach_quota, $friendlyquota);
		if($usage['ausage'] !== NULL)
		{
			$friendlyusage = get_friendly_size($usage['ausage']);
			$lang->attach_usage = $lang->sprintf($lang->attach_usage, $friendlyusage);
			eval("\$link_viewattachments = \"".$templates->get("post_attachments_viewlink")."\";");
		}
		else
		{
			$lang->attach_usage = "";
		}

		if($mybb->settings['maxattachments'] == 0 || ($mybb->settings['maxattachments'] != 0 && $attachcount < $mybb->settings['maxattachments']) && !$noshowattach)
		{
			eval("\$attach_add_options = \"".$templates->get("post_attachments_add")."\";");
		}
		if(($mybb->usergroup['caneditattachments'] || $forumpermissions['caneditattachments']) && $attachcount > 0)
		{
			eval("\$attach_update_options = \"".$templates->get("post_attachments_update")."\";");
		}
		if($attach_add_options || $attach_update_options)
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

	$query = $db->simple_select("settinggroups", "gid", "name = 'mydropzone'", ["limit" => 1]);
	$gid = (int) $db->fetch_field($query, "gid");

	return $gid;
}

if (!function_exists('control_object')) {
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