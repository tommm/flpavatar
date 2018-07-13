<?php
/*
+---------------------------------------------------------------------------
|	Optimized First and Last Post Avatar
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

// Set optimized avatar directory
define('OPTIMIZED_AV', 'uploads'.DIRECTORY_SEPARATOR.'avatars'.DIRECTORY_SEPARATOR.'optimized');

// Set this if you want to override the maximum post size, not really needed
//define('MAX_FP_SIZE', '100x100');

global $mybb;
function flpavatar_info()
{
	global $lang;
	$lang->load('flpavatar');

	return array(
		'name' => $lang->flp_name,
		'description' => $lang->flp_desc,
		'website' => 'https://github.com/tommm/flpavatar',
		'author' => 'Tomm',
		'authorsite' => 'http://xekko.co.uk',
		'version' => '1.0.2',
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
	$mybb->settings['flp_avail'] = explode(',', $mybb->settings['flp_avail']);
	foreach($mybb->settings['flp_avail'] as $key => $value)	$mybb->settings['flp_avail'][$key] = trim($value);

	$plugins->add_hook('build_forumbits_forum', 'flpavatar_forumlist');
	$plugins->add_hook('forumdisplay_thread', 'flpavatar_threadlist');

	$plugins->add_hook('postbit_announcement', 'flpavatar_thread');
	$plugins->add_hook('announcements_end', 'flpavatar_end');

	$plugins->add_hook('postbit', 'flpavatar_thread');
	$plugins->add_hook('showthread_end', 'flpavatar_end');

	// Search
	if(THIS_SCRIPT == 'search.php' && in_array('search', $mybb->settings['flp_avail']))
	{
		define('IN_SEARCH', 1);
		$plugins->add_hook('search_results_thread', 'flpavatar_threadlist');
		$plugins->add_hook('search_results_post', 'flpavatar_threadlist');
	}

	// Maintenance
	$plugins->add_hook('forumdisplay_announcement', 'flpavatar_anno');
	$plugins->add_hook('usercp_do_avatar_end', 'flpavatar_avatar_update');

	if(THIS_SCRIPT == 'modcp.php' && in_array($mybb->input['action'], array('do_new_announcement', 'do_edit_announcement')) && in_array('forumdisplay', $mybb->settings['flp_avail']))
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

	if(!in_array('forumindex', $mybb->settings['flp_avail']))
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
				$avatar = flp_format_avatar($user);
				
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

		if($flp_type == 1 && !in_array('forumdisplay', $mybb->settings['flp_avail']) || $flp_type == 2 && !in_array('search', $mybb->settings['flp_avail']))
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
					$flp_cache[$user['uid']] = flp_format_avatar($user);
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
					$flp_cache[$user['uid']] = flp_format_avatar($user);
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

	if(isset($flp_avatar) || $thread['firstpost'] != $post['pid'] || !in_array('showthread', $mybb->settings['flp_avail']))
	{
		return; // This is not the post you are looking for...
	}

	$flp_avatar = flp_format_avatar($post);
}

function flpavatar_end()
{
	global $db, $flp_avatar, $mybb, $thread;

	if(!in_array('showthread', $mybb->settings['flp_avail']))
	{
		return;
	}

	// Fallback if the firstposter isn't on the thread page
	if(!isset($flp_avatar) || !is_array($flp_avatar))
	{
		$uid = intval($thread['uid']);
		$query = $db->simple_select('users', 'uid, username, username AS userusername, avatar, avatardimensions', "uid = '{$uid}'");

		$user = $db->fetch_array($query);
		$flp_avatar = flp_format_avatar($user);
	}
}

// Upkeep of user's avatar
function flpavatar_avatar_update()
{
	global $cache, $db, $extra_user_updates, $mybb, $updated_avatar, $user;

    $user = ($user) ? $user : $mybb->user;
    $inline_avatars = $cache->read('inline_avatars');
	$optimized_avatars = glob(MYBB_ROOT.OPTIMIZED_AV.DIRECTORY_SEPARATOR.$user['uid'].".*");
	
	// Flash optimized avatars
	if(!empty($optimized_avatars))
	{
		foreach($optimized_avatars as $optimized_avatar)
		{
			unlink($optimized_avatar);
		}
	}

    if(!$inline_avatars[$user['uid']])
    {
        return; // No need to keep this inline as we'll never use it
    }

    $update = ($extra_user_updates) ? $extra_user_updates : $updated_avatar;

    if(is_array($update))
    {
        $user = array_merge($user, $update);    

        $inline_avatars[$user['uid']] = flp_format_avatar($user);
        $cache->update('inline_avatars', $inline_avatars);
    }
} 

// Detection for announcements
function flpavatar_anno()
{
	global $announcement, $cache, $flp_avatar, $mybb;

	if(!in_array('forumdisplay', $mybb->settings['flp_avail']))
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
			'profile' => $inline_avatars[$announcement['uid']]['profile']
		);
	}
}

function flpavatar_anno_update($args)
{
	global $cache, $db, $insert_announcement, $mybb, $update_announcement;

	$inline_avatars = $cache->read('inline_avatars');
	$anno = ($update_announcement) ? $update_announcement : $insert_announcement;

	if(is_array($inline_avatars) && $inline_avatars[$anno['uid']])
	{
		return; // No need to re-cache
	}

	if($anno['uid'] == $mybb->user['uid'])
	{
		$inline_avatars[$anno['uid']] = flp_format_avatar($mybb->user);
	}
	else
	{
		$query = $db->simple_select('users', 'uid, username, username AS userusername, avatar, avatardimensions', "uid = '{$anno['uid']}'");
		$user = $db->fetch_array($query);

		$inline_avatars[$user['uid']] = flp_format_avatar($user);
	}

	$cache->update('inline_avatars', $inline_avatars);
}

// For private messages and tracking
function flpavatar_private_end()
{
	global $db, $messagelist, $mybb, $unreadmessages, $readmessages;

	if(!in_array('private', $mybb->settings['flp_avail']))
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
			$parameters = flp_format_avatar($user);

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

function flp_format_avatar($user)
{
	global $mybb;

	if(empty($user['avatar']))
	{
		// Avatar not assigned. Set optimized default
		if(!is_file(MYBB_ROOT.OPTIMIZED_AV.DIRECTORY_SEPARATOR."0.png")) flpavatar_optimize(MYBB_ROOT."images/default_avatar.png");
		$user['avatar'] = $mybb->asset_url.DIRECTORY_SEPARATOR.OPTIMIZED_AV.DIRECTORY_SEPARATOR."0.png";
	}
	else if((int)$user['uid'] > 0)
	{
		// Check for availability of optimized avatar and if available assign it
		$avatar = glob(MYBB_ROOT.OPTIMIZED_AV.DIRECTORY_SEPARATOR.$user['uid'].".*");
		if(!empty($avatar))
		{
			$asset_url = $mybb->asset_url;
			if(!empty($mybb->settings['flp_cdnlink'])) // CDN link is provided, route through it
			{
				$asset_url = $mybb->settings['flp_cdnlink'];
				$asset_url = (substr($asset_url, -1) == '/') ? substr($asset_url, 0, -1) : $asset_url;
			}
			$user['avatar'] = str_replace(MYBB_ROOT, $asset_url.DIRECTORY_SEPARATOR, $avatar[0]);
		}
		else
		{
			// Avatar assigned, but optimised avatar not available -> Create and assign
			$opts = flpavatar_default_size();
			$opts['name'] = $user['uid'];
			$opts['crop'] = (int)$mybb->settings['flp_crop'];
			$opts['quality'] = (int)$mybb->settings['flp_quality'];

			// Assign from local for the first time to give some time to CDN sync
			$user['avatar'] = str_replace(MYBB_ROOT, $mybb->asset_url.DIRECTORY_SEPARATOR, flpavatar_optimize($user['avatar'], $opts));
		}
	}

	if($mybb->version_code >= 1700)
	{
		// 1.8 has a slightly different syntax
		$dimensions = ($user['avatar']) ? $user['avatardimensions'] : flpavatar_default_size(1);
		$size = (defined('MAX_FP_SIZE')) ? MAX_FP_SIZE : $mybb->settings['postmaxavatarsize'];

		$avatar = format_avatar($user['avatar'], $dimensions, $size);

		$return = array(
			'avatar' => $avatar['image'],
			'dimensions' => $avatar['width_height'],
			'username' => $user['username'],
			'profile' => get_profile_link($user['uid'])
		);
	}
	else
	{
		$return = format_avatar($user);
	}

	// Dimension strict mode
	if($mybb->settings['flp_strictsize'])
	{
		$dimensions = [];
		$defined = flpavatar_default_size();
		$postmaxavatarsize = explode('x', $mybb->settings['postmaxavatarsize']);
		$dimensions['width'] = [(int)$defined['width'], (int)$postmaxavatarsize[0]];
		$dimensions['height'] = [(int)$defined['height'], (int)$postmaxavatarsize[1]];
		if(defined('MAX_FP_SIZE'))
		{
			$maxfpsize = explode('x', MAX_FP_SIZE);
			$dimensions['width'][] = (int)$maxfpsize[0];
			$dimensions['height'][] = (int)$maxfpsize[1];
		}
		$dimensions['width'] = min($dimensions['width']);
		$dimensions['height'] = min($dimensions['height']);

		$return['dimensions'] = " width='{$dimensions['width']}' height='{$dimensions['height']}'";
	}

	return $return;
}

// format_avatar is a 1.8 function; create it if our party doesn't have >= 1.7 (LOSERS, hah).
if(!function_exists('format_avatar'))
{
	function format_avatar($user)
	{
		global $mybb;
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
		$avatar = ($user['avatar']) ? htmlspecialchars_uni($user['avatar']) : $mybb->asset_url.'/images/default_avatar.png';

		$avatar_width_height = '';
		$dimensions = explode('|', ($user['avatar']) ? $user['avatardimensions'] : flpavatar_default_size(1));

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

function flpavatar_optimize($image, $options = [])
{
	global $mybb;

	$image_type = ['1'=>'gif', '2'=>'jpg', '3'=>'png', '6'=>'bmp'];
	$ext = 'jpg';

	$defaults['name'] = '0';
	$defaults['width'] = 44;
	$defaults['height'] = 44;
	$defaults['crop'] = 0;
	$defaults['quality'] = 9; // This value set the compression level: 0-9 or -1, where 0 is NO COMPRESSION at all, 1 is FASTEST but produces larger files, 9 provides the best compression (smallest files) but takes a long time to compress, and -1 selects the default compiled into the zlib library.

	$options = array_merge($defaults, $options);

	// Detect if the image path is remote, download if yes 
	$parsed_url = parse_url($image);
	if(isset($parsed_url["scheme"]) && strpos($image, $mybb->asset_url) === false)
	{
		$temp = OPTIMIZED_AV.DIRECTORY_SEPARATOR."temp";
		
		$image = file_get_contents($image);
		if(@file_put_contents($temp, $image))
		{
			$image = $temp;
			$ext = $image_type[exif_imagetype($temp)];
		}
	}
	else // Its a local file
	{
		$ext = pathinfo($image, PATHINFO_EXTENSION);
	}
	// Remove stamp, if any
	$image = explode("?", $image)[0];
	$ext = explode("?", $ext)[0];

	// Start resizing
	list($width, $height) = @getimagesize($image);
	$w = (int)$options['width'];
	$h = (int)$options['height'];
	if($width && $height) $r = $width / $height;
	if ($options['crop'] == 1) {
		if ($width > $height) {
			$width = ceil($width-($width*abs($r-$w/$h)));
		} else {
			$height = ceil($height-($height*abs($r-$w/$h)));
		}
		$new_width = $w;
		$new_height = $h;
	} else {
		if ($w/$h > $r) {
			$new_width = $h*$r;
			$new_height = $h;
		} else {
			$new_height = $w/$r;
			$new_width = $w;
		}
	}

	switch($ext){
		case "bmp":
			$src = @imagecreatefromwbmp($image);
		break;
		case "png":
			$src = @imagecreatefrompng($image);
		break;
		case "gif":
			$src = @imagecreatefromgif($image);
		break;
		case "jpeg":
		case "jpg":
		default:
			$src = @imagecreatefromjpeg($image);
		break;
	}

	$raw_image = @imagecreatetruecolor($new_width, $new_height);
	@imagealphablending($raw_image, false);
	@imagesavealpha($raw_image, true);
	@imagefilledrectangle($raw_image, 0, 0, (int)$width, (int)$height, @imagecolorallocatealpha($raw_image, 255, 255, 255, 127));
	@imagecopyresampled($raw_image, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
	$new_image = OPTIMIZED_AV.DIRECTORY_SEPARATOR.$options['name'].".".$ext;
	$qly = (int)$options['quality'];

	switch($ext){
		case "bmp":
			@imagewbmp($raw_image, $new_image, $qly);
		break;
		case "png":
			@imagepng($raw_image, $new_image, $qly);
		break;
		case "gif":
			@imagegif($raw_image, $new_image, $qly);
		break;
		case "jpeg":
		case "jpg":
		default:
			@imagejpeg($raw_image, $new_image, $qly);
		break;
	}

	// Destroy temporary file, if exists
	if(isset($temp) && is_file($temp)) unlink($temp);

	return $new_image;
}

function flpavatar_default_size($joined = 0)
{
	global $mybb;
	$size['width'] = $size['height'] = 44; // Default value
	$defined_size = trim($mybb->settings['flp_avatarsize']);
	if(preg_match('/^\d+\|\d+$/', $defined_size))
	{
		$pix = explode("|", $defined_size);
		if((int)$pix[0] > 0 && (int)$pix[1] > 0)
		{
			$size['width'] = (int)$pix[0];
			$size['height'] = (int)$pix[1];
		}
	}
	return ($joined) ? $size['width'].'|'.$size['height'] : $size;
}
