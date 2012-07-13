<?php
/*
+---------------------------------------------------------------------------
|	First and Last Post Avatar
|	ACP Functions
|	=============================================
|	by Tom Moore (www.xekko.co.uk)
|	Copyright 2012 Mooseypx Design / Xekko
|	=============================================
+---------------------------------------------------------------------------
*/

if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

// General Hooks
$plugins->add_hook('admin_user_users_edit_commit', 'flpavatar_avatar_update');
$plugins->add_hook('admin_user_users_avatar_gallery_commit', 'flpavatar_avatar_update');

// Specific ACP actions
if($mybb->input['module'] == 'config-settings' && $mybb->input['action'] == 'change' && $mybb->input['gid'] == 7)
{
	// Saving or loading permissions
	$plugins->add_hook('admin_formcontainer_output_row', 'flpavatar_permissions');
	$plugins->add_hook('admin_config_settings_change', 'flpavatar_permissions_save');
}

function flpavatar_install()
{
	global $cache, $db;

	flpavatar_uninstall();

	$query = $db->simple_select('settings', 'MAX(disporder) as disporder', "gid = '7'");
	$disporder = $db->fetch_field($query, 'disporder');

	$insert_array = array(
		'name' => 'flp_permissions',
		'title' => 'First and Last Post Avatars',
		'description' => 'Options for displaying first and last post avatars across the forum.',
		'optionscode' => 'text',
		'value' => '0,0,0,0',
		'disporder' => ++$disporder,
		'gid' => 7,
		'isdefault' => 0
	);

	$db->insert_query('settings', $insert_array);
	rebuild_settings();

	// Build inline avatars
	$query = $db->simple_select('announcements', 'uid');
	$query = $db->query("
		SELECT DISTINCT(a.uid) as uid, u.username, username AS userusername, avatar, avatardimensions
		FROM ".TABLE_PREFIX."announcements a
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = a.uid)
	");

	if($db->num_rows($query))
	{
		$inline_avatars = array();
		while($user = $db->fetch_array($query))
		{
			$inline_avatars[$user['uid']] = format_avatar($user);
		}

		$cache->update('inline_avatars', $inline_avatars);
	}
}

function flpavatar_uninstall()
{
	global $cache, $db;

	// $cache->delete('inline_avatars'); // Exclusively for 1.8 users

	$db->delete_query('datacache', "title = 'inline_avatars'");
	$db->delete_query('settings', "name IN ('flp_permissions')");

	rebuild_settings();
}

function flpavatar_is_installed()
{
	global $mybb;

	if($mybb->settings['flp_permissions'])
	{
		return true;
	}

	return false;
}

function flpavatar_permissions(&$row)
{
	global $form, $mybb;

	if($row['row_options']['id'] != 'row_setting_flp_permissions')
	{
		return;
	}

	$permissions = explode(',', $mybb->settings['flp_permissions']);

	$setting = array(
		'forumdisplay' => array('name' => 'Forum Display', 'description' => 'Generate first and last post avatar information on Forum Display', 'value' => $permissions[0]),
		'index' => array('name' => 'Forum Index', 'description' => 'Generate last post avatar information on the Forum Index', 'value' => $permissions[1]),
		'search' => array('name' => 'Search Results', 'description' => 'Generate first and last post avatar information on Search Results', 'value' => $permissions[2]),
		'showthread' => array('name' => 'Show Thread', 'description' => 'Generate first post information on Show Thread', 'value' => $permissions[3]),
	);

	$i = 1;
	$row['content'] = '';
	foreach($setting as $v => $p)
	{
		$row['content'] .= $form->generate_check_box("flp_permissions[{$v}]", 1, $p['name'], array('id' => "setting_flp_permissions_{$i}", 'class' => "setting_flp_permissions_{$i}", 'checked' => $p['value']));
		$row['content'] .= "<div class='description' style='margin: 0 0 10px 25px;'>{$p['description']}</div>";
		++$i;
	}
}

function flpavatar_permissions_save()
{
	global $mybb;

	if($mybb->request_method != 'post')
	{
		return;
	}

	$values = array();
	$permissions = array('forumdisplay', 'index', 'search', 'showthread');

	foreach($permissions as $setting)
	{
		$values[] = (isset($mybb->input['flp_permissions'][$setting])) ? intval($mybb->input['flp_permissions'][$setting]) : 0;
	}

	$mybb->input['upsetting']['flp_permissions'] = implode(',', $values);
}