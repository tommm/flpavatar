<?php
/*
+---------------------------------------------------------------------------
|	First and Last Post Avatar
|	ACP Functions
|	=============================================
|	by Tom Moore (www.xekko.co.uk)
|	Copyright 2012 Mooseypx Design / Xekko
|
|	Edited by: effone
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
$plugins->add_hook('admin_config_settings_change_commit', 'flpavatar_flashthumbs');

function flpavatar_install()
{
	global $cache, $db, $lang;
	$lang->load('flpavatar');

	flpavatar_uninstall();

	// Check and create the directory to hold optimized avatars
	if (!is_dir(MYBB_ROOT.OPTIMIZED_AV))
	{
		mkdir(MYBB_ROOT.OPTIMIZED_AV, 0777, true);
	}
	
	// Settings group array details
	$group = array(
		'name' => 'flp_avatar',
		'title' => $db->escape_string($lang->flp_name),
		'description' => $db->escape_string($lang->setting_group_flp_desc),
		'isdefault' => 0
	);

	// Check if the group already exists.
	$query = $db->simple_select('settinggroups', 'gid', "name='flp_avatar'");

	if($gid = (int)$db->fetch_field($query, 'gid'))
	{
		// We already have a group. Update title and description.
		$db->update_query('settinggroups', $group, "gid='{$gid}'");
	}
	else
	{
		// We don't have a group. Create one with proper disporder.
		$query = $db->simple_select('settinggroups', 'MAX(disporder) AS disporder');
		$disporder = (int)$db->fetch_field($query, 'disporder');

		$group['disporder'] = ++$disporder;

		$gid = (int)$db->insert_query('settinggroups', $group);
	}

	// add settings
	$settings = array(
		'crop'	=> array(
			'optionscode'	=> 'yesno',
			'value'			=> 0
		),
		'avatarsize'=> array(
			'optionscode'	=> 'text',
			'value'			=> "44|44"
		),
		'strictsize'	=> array(
			'optionscode'	=> 'yesno',
			'value'			=> 0
		),
		'quality'	=> array(
			'optionscode'	=> "select \n 0=".$lang->flp_quality_asis." \n -1=".$lang->flp_quality_default." \n 1=".$lang->flp_quality_good." \n 5=".$lang->flp_quality_medium." \n 9=".$lang->flp_quality_lossy,
			'value'			=> -1
		),
		'cdnlink'	=> array(
			'optionscode'	=> 'text',
			'value'			=> ""
		),
		'avail'	=> array(
			'optionscode'	=> "checkbox \n forumindex=Forum Index ² \n forumdisplay=Forum Display ³ \n showthread=Show Thread ¹ \n search=Search Results ³ \n private=Private Messages",
			'value'			=> ""
		)
	);

	$disporder = 0;

	// Create and/or update settings.
	foreach($settings as $key => $setting)
	{
		// Prefix all keys with group name.
		$key = "flp_{$key}";

		$lang_var_title = "setting_{$key}";
		$lang_var_description = "setting_{$key}_desc";

		$setting['title'] = $lang->{$lang_var_title};
		$setting['description'] = $lang->{$lang_var_description};

		// Filter valid entries.
		$setting = array_intersect_key($setting,
			array(
				'title' => 0,
				'description' => 0,
				'optionscode' => 0,
				'value' => 0,
		));

		$setting = array_map(array($db, 'escape_string'), $setting);

		++$disporder;

		$setting = array_merge(
			array('description' => '',
				'optionscode' => 'yesno',
				'value' => 0,
				'disporder' => $disporder),
		$setting);

		$setting['name'] = $db->escape_string($key);
		$setting['gid'] = $gid;

		$query = $db->simple_select('settings', 'sid', "gid='{$gid}' AND name='{$setting['name']}'");

		if($sid = $db->fetch_field($query, 'sid'))
		{
			unset($setting['value']);
			$db->update_query('settings', $setting, "sid='{$sid}'");
		}
		else
		{
			$db->insert_query('settings', $setting);
		}
	}

	rebuild_settings();
	flpavatar_buildinline();
}

function flpavatar_uninstall()
{
	global $cache, $db;
	
	// Remove the directory to holding optimized avatars
	$dir = MYBB_ROOT.OPTIMIZED_AV;
	if (is_dir($dir))
	{
		array_map('unlink', glob("$dir".DIRECTORY_SEPARATOR."*.*"));
		rmdir($dir);
	}

	// Delete settings group
	$db->delete_query('settinggroups', "name='flp_avatar'");

	// Remove the settings
	$db->delete_query('settings', "name IN ('flp_crop', 'flp_avatarsize', 'flp_strictsize', 'flp_quality', 'flp_cdnlink', 'flp_avail')");
	
	// $cache->delete('inline_avatars'); // Exclusively for 1.8 users

	$db->delete_query('datacache', "title = 'inline_avatars'");

	rebuild_settings();
}

function flpavatar_activate()
{
	global $db, $mybb;
/*
	$info = flpavatar_info();
	if(version_compare($info['version'], '1.0.1', '<='))
	{
		$permissions = explode(',', $mybb->settings['flp_permissions']);

		if(count($permissions) == 4)
		{
			// Missing our PM setting
			$permissions[] = 0;
			$db->update_query('settings', array('value' => implode(',', $permissions)), "name = 'flp_permissions'");

			rebuild_settings();
		}
	}
	*/
	flpavatar_buildinline();
}

function flpavatar_is_installed()
{
	global $mybb;

	if(isset($mybb->settings['flp_avail']))
	{
		return true;
	}

	return false;
}

function flpavatar_buildinline()
{
	global $db, $cache;
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
			$inline_avatars[$user['uid']] = flp_format_avatar($user);
		}

		$cache->update('inline_avatars', $inline_avatars);
	}
}

function flpavatar_flashthumbs()
{
	// Settings changed, cleanup generated optimized avatars
	$dir = MYBB_ROOT.OPTIMIZED_AV;
	if(is_dir($dir)) array_map('unlink', glob("$dir".DIRECTORY_SEPARATOR."*.*"));
}