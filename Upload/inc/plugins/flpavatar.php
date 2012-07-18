<?php
/*
+---------------------------------------------------------------------------
|	First and Last Post Avatar
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

global $mybb;
function flpavatar_info()
{
	return array(
		'name' => 'Last/First Post Avatar',
		'description' => 'A plugin that allows the first and last post avatar around the forum.',
		'website' => 'http://resources.xekko.co.uk',
		'author' => 'Tomm',
		'authorsite' => 'http://xekko.co.uk',
		'version' => '1.0.1',
		'guid' => '9fbd88aaa25448e1bd26ca5b523a0dcd',
		'compatibility' => '*'
	);
}

if(defined('IN_ADMINCP'))
{
	require_once MYBB_ROOT.'inc/plugins/flpavatar_acp.php';
}
else
{
	$exp = explode(',', $mybb->settings['flp_permissions']);
	$mybb->settings['flp_permissions'] = array('forumdisplay' => $exp[0], 'index' => $exp[1], 'pm' => $exp[4], 'search' => $exp[2], 'showthread' => $exp[3]);

	$plugins->add_hook('build_forumbits_forum', 'flpavatar_forumlist');
	$plugins->add_hook('forumdisplay_thread', 'flpavatar_threadlist');

	$plugins->add_hook('postbit_announcement', 'flpavatar_thread');
	$plugins->add_hook('announcements_end', 'flpavatar_end');

	$plugins->add_hook('postbit', 'flpavatar_thread');
	$plugins->add_hook('showthread_end', 'flpavatar_end');

	// Search
	if(THIS_SCRIPT == 'search.php' && $mybb->settings['flp_permissions']['search'])
	{
		define('IN_SEARCH', 1);
		$plugins->add_hook('search_results_thread', 'flpavatar_threadlist');
		$plugins->add_hook('search_results_post', 'flpavatar_threadlist');
	}

	// Maintenance
	$plugins->add_hook('forumdisplay_announcement', 'flpavatar_anno');
	$plugins->add_hook('usercp_do_avatar_end', 'flpavatar_avatar_update');
	if(THIS_SCRIPT == 'modcp.php' && in_array($mybb->input['action'], array('do_new_announcement', 'do_edit_announcement')) && $mybb->settings['flp_permissions']['forumdisplay'])
	{
		$plugins->add_hook('redirect', 'flpavatar_anno_update');
	}

	if(THIS_SCRIPT == 'private.php')
	{
		$plugins->add_hook('private_end', 'flpavatar_private_end');
		$plugins->add_hook("private_results_end", "flpavatar_private_end");
		$plugins->add_hook("private_tracking_end", "flpavatar_private_end");
	}
}

// For forum list
function flpavatar_forumlist(&$_f)
{
	global $cache, $db, $fcache, $mybb;

	if(!$mybb->settings['flp_permissions']['index'])
	{
		return;
	}

	if(!isset($cache->cache['flp_cache']))
	{
		$cache->cache['flp_cache'] = array();
		$flp_cache = $cache->read('flp_cache');

		$forums = new RecursiveIteratorIterator(new RecursiveArrayIterator($fcache));

		// This loop goes through each forum and finds the right lastposter
		foreach($forums as $_forum)
		{
			$forum = $forums->getSubIterator();

			if($forum['fid'])
			{
				$forum = iterator_to_array($forum);
				$flp_cache[$forum['fid']] = $forum;

				if($forum['parentlist'])
				{
					$flp_cache[$forum['fid']] = $forum;
					$flp_cache[$forum['fid']]['avataruid'] = $forum['lastposteruid'];

					$exp = array_reverse(explode(',', $forum['parentlist']));

					foreach($exp as $parent)
					{
						if($parent == $forum['fid']) continue;
						if(isset($flp_cache[$parent]) && $forum['lastpost'] > $flp_cache[$parent]['lastpost'])
						{
							$flp_cache[$parent]['lastpost'] = $forum['lastpost'];
							$flp_cache[$parent]['avataruid'] = $forum['lastposteruid']; // Bubble up to replace parent lastpost
						}
					}
				}
			}
		}

		// This loop gathers lastpost users and sorts by user/forums
		$users = array();
		foreach($flp_cache as $forum)
		{
			if(isset($forum['avataruid']))
			{
				$users[$forum['avataruid']][] = $forum['fid'];
			}
		}

		// Third loop; this retrieves above users' avatar info
		if(!empty($users))
		{
			$sql = implode(',', array_keys($users));
			$query = $db->simple_select('users', 'uid, username, username AS userusername, avatar, avatardimensions', "uid IN ({$sql})");

			while($user = $db->fetch_array($query))
			{
				// Finally, assign avatars
				$avatar = format_avatar($user);
				foreach($users[$user['uid']] as $fid)
				{
					$flp_cache[$fid]['flp_avatar'] = $avatar;
				}
			}
		}

		// Encore! Replace our inline cache
		$cache->cache['flp_cache'] = $flp_cache;
	}

	$_f['flp_lastpost'] = $cache->cache['flp_cache'][$_f['fid']]['flp_avatar'];
}

// For thread list (in search and forumdisplay)
function flpavatar_threadlist()
{
	global $db, $flp_avatar, $flp_firstpost, $flp_lastpost, $mybb, $post, $search, $thread, $threadcache, $thread_cache;
	static $flp_cache, $flp_type;

	if(!isset($flp_cache))
	{
		$users = $flp_cache = array();
		$flp_type = (defined('IN_SEARCH')) ? 2 : 1;
		$cache = ($thread_cache) ? $thread_cache : $threadcache;

		if($flp_type == 1 && !$mybb->settings['flp_permissions']['forumdisplay'] || $flp_type == 2 && !$mybb->settings['flp_permissions']['search'])
		{
			$flp_cache = array();
			return;
		}

		if(isset($cache))
		{
			// Handling threadlist or search results in threads
			foreach($cache as $t)
			{
				if(!in_array($t['uid'], $users))
				{
					$users[] = "'".intval($t['uid'])."'"; // The original author of the thread
				}

				if(!in_array($t['lastposteruid'], $users))
				{
					$users[] = "'".intval($t['lastposteruid'])."'"; // The lastposter (if they aren't the original author)
				}
			}

			if(!empty($users))
			{
				$sql = implode(',', $users);
				$query = $db->simple_select('users', 'uid, username, username AS userusername, avatar, avatardimensions', "uid IN ({$sql})");

				while($user = $db->fetch_array($query))
				{
					$flp_cache[$user['uid']] = format_avatar($user);
				}
			}
		}
		elseif(isset($post) && isset($search))
		{
			// Handling search results in posts
			$flp_type = 3;

			$query = $db->query("
				SELECT u.uid, u.username, u.username as userusername, u.avatar, u.avatardimensions
				FROM ".TABLE_PREFIX."posts p
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = p.uid)
				WHERE p.pid IN ({$search['posts']})
			");

			while($user = $db->fetch_array($query))
			{
				if(!isset($flp_cache[$user['uid']]))
				{
					$flp_cache[$user['uid']] = format_avatar($user);
				}
			}
		}
	}

	if(empty($flp_cache))
	{
		return; // There are no users with avatars
	}

	$uid = ($post['uid']) ? $post['uid'] : $thread['uid']; // Always have an author

	if(isset($flp_cache[$uid]))
	{
		$flp_avatar = $flp_cache[$uid];
	}

	if(isset($flp_cache[$thread['lastposteruid']]))
	{
		$flp_lastpost = $flp_cache[$thread['lastposteruid']]; // Specific for lastposters
	}
}

// For threads
function flpavatar_thread(&$post)
{
	global $flp_avatar, $mybb, $thread;

	if(isset($flp_avatar) || $thread['firstpost'] != $post['pid'] || !$mybb->settings['flp_permissions']['showthread'])
	{
		return; // This is not the post you are looking for...
	}

	$flp_avatar = format_avatar($post);
}

function flpavatar_end()
{
	global $db, $flp_avatar, $mybb, $thread;

	if(!$mybb->settings['flp_permissions']['showthread'])
	{
		return;
	}

	// Fallback if the firstposter isn't on the thread page
	if(!isset($flp_avatar) || !is_array($flp_avatar))
	{
		$uid = intval($thread['uid']);
		$query = $db->simple_select('users', 'uid, username, username AS userusername, avatar, avatardimensions', "uid = '{$uid}'");

		$user = $db->fetch_array($query);
		$flp_avatar = format_avatar($user);
	}
}

// Upkeep of user's avatar
function flpavatar_avatar_update()
{
	global $cache, $db, $extra_user_updates, $mybb, $updated_avatar, $user;

	$inline_avatars = $cache->read('inline_avatars');
	$user = array_merge(($user) ? $user : $mybb->user, ($extra_user_updates) ? $extra_user_updates : $updated_avatar);

	if(!$inline_avatars[$user['uid']])
	{
		return; // No need to keep this inline as we'll never use it
	}

	$inline_avatars[$user['uid']] = format_avatar($user);
	$cache->update('inline_avatars', $inline_avatars);
}

// Detection for announcements
function flpavatar_anno()
{
	global $announcement, $cache, $flp_avatar, $mybb;

	if(!$mybb->settings['flp_permissions']['forumdisplay'])
	{
		return;
	}

	$inline_avatars = $cache->read('inline_avatars');

	if($inline_avatars[$announcement['uid']])
	{
		$flp_avatar = array(
			'avatar' => $inline_avatars[$announcement['uid']]['avatar'],
			'dimensions' => $inline_avatars[$announcement['uid']]['dimensions'],
			'username' => $announcement['username'],
			'profile' => $announcement['profilelink']
		);
	}
}

function flpavatar_anno_update($args)
{
	global $cache, $db, $insert_announcement, $mybb, $update_announcement;

	$inline_avatars = $cache->read('inline_avatars');
	$anno = ($update_announcement) ? $update_announcement : $insert_announcement;

	if($inline_avatars[$anno['uid']])
	{
		return; // No need to re-cache
	}

	if($anno['uid'] == $mybb->user['uid'])
	{
		$inline_avatars[$anno['uid']] = format_avatar($mybb->user);
	}
	else
	{
		$query = $db->simple_select('users', 'uid, username, username AS userusername, avatar, avatardimensions', "uid = '{$anno['uid']}'");
		$user = $db->fetch_array($query);

		$inline_avatars[$user['uid']] = format_avatar($user);
	}

	$cache->update('inline_avatars', $inline_avatars);
}

// For private messages and tracking
function flpavatar_private_end()
{
	global $db, $messagelist, $unreadmessages, $readmessages;

	if(!$mybb->settings['flp_permissions']['pm'])
	{
		return;
	}

	$users = array();
	foreach(array($messagelist, $unreadmessages, $readmessages) as $content)
	{
		if(!$content) continue;

		preg_match_all('#<flp_avatar\[([0-9]+)\]#', $content, $matches);

		if(is_array($matches[1]) && !empty($matches[1]))
		{
			foreach($matches[1] as $user)
			{
				if(!intval($user)) continue;
				$users[] = intval($user);
			}
		}
	}

	if(!empty($users))
	{
		$sql = implode(',', $users);
		$query = $db->simple_select('users', 'uid, username, username AS userusername, avatar, avatardimensions', "uid IN ({$sql})");

		$find = $replace = array();
		while($user = $db->fetch_array($query))
		{
			$parameters = format_avatar($user);

			foreach($parameters as $piece => $cake)
			{
				$find[] = "<flp_avatar[{$user['uid']}]['{$piece}']>";
				$replace[] = $cake;
			}
		}

		if(isset($messagelist)) $messagelist = str_replace($find, $replace, $messagelist);
		if(isset($readmessages)) $readmessages = str_replace($find, $replace, $readmessages);
		if(isset($unreadmessages)) $unreadmessages = str_replace($find, $replace, $unreadmessages);
	}
}

// format_avatar is a 1.8 function; create it if our party doesn't have >= 1.7 (LOSERS, hah).
if(!function_exists('format_avatar'))
{
	function format_avatar($user)
	{
		global $cache, $mybb;
		static $users;

		if(!isset($users))
		{
			$users = array();
		}

		if(isset($users[$user['uid']]))
		{
			return $users[$user['uid']];
		}

		$size = (defined('MAX_FP_SIZE')) ? MAX_FP_SIZE : $mybb->settings['postmaxavatarsize'];
		$avatar = ($user['avatar']) ? htmlspecialchars_uni($user['avatar']) : $mybb->settings['bburl'].'/images/default_avatar.gif';

		$avatar_width_height = '';
		$dimensions = explode('|', ($user['avatar']) ? $user['avatardimensions'] : '44|44'); // 44|44 must match the default_avatar image

		if(is_array($dimensions) && $dimensions[0] && $dimensions[1])
		{
			$avatar_width_height = " width='{$dimensions[0]}' height='{$dimensions[1]}'";

			list($max_width, $max_height) = explode('x', $size);
			if($dimensions[0] > $max_width || $dimensions[1] > $max_height)
			{
				require_once MYBB_ROOT.'inc/functions_image.php';

				$scaled_dimensions = scale_image($dimensions[0], $dimensions[1], $max_width, $max_height);
				$avatar_width_height = " width='{$scaled_dimensions['width']}' height='{$scaled_dimensions['height']}'";
			}
		}

		$users[$user['uid']] = array(
			'avatar' => $avatar,
			'dimensions' => $avatar_width_height,
			'username' => htmlspecialchars_uni($user['userusername']),
			'profile' => get_profile_link($user['uid']),
		);

		return $users[$user['uid']];
	}
}