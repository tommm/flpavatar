<?php
/**
 * ACP Language file for MyBB Plugin : "Optimised First / Last Post Avatar"
 * Author: effone (https://eff.one)
 * 
 * Original Plugin Author: Tomm
 * Original Plugin Link: https://github.com/tommm/flpavatar
 *
 * Website: http://xekko.co.uk/
 * License: http://xekko.co.uk/service-licence.html
 *
 */

$l['flp_name'] = 'Optimised First / Last Post Avatar';
$l['flp_desc'] = 'A plugin that allows optimized first and last post avatar around the forum.<br /><i>Modified by <a href="https://eff.one">effone</a></i>';

$l['setting_group_flp_desc'] = 'Various settings for Optimised First / Last Post Avatar.';

$l['setting_flp_crop'] = 'Crop Avatar?';
$l['setting_flp_crop_desc'] = 'Crop the avatar image to match the defined size while compressing / resizing?';

$l['setting_flp_avatarsize'] = 'Avatar Dimension';
$l['setting_flp_avatarsize_desc'] = 'Dimension of the avatars to be scaled down in. Width and height to be separated with "|", for example \'50|50\'.<br /><i>Note:</i> Wrong input or pattern will default the value to \'44|44\'';

$l['setting_flp_strictsize'] = 'Avatar Dimension Strict Mode';
$l['setting_flp_strictsize_desc'] = 'Strictly adhere above-defined avatar dimension regardless of original avatar aspect ratio.<br /><i>Note:</i> If the defined dimension is greater than maximum allowed dimension, the lower will be considered.';

$l['setting_flp_quality'] = 'Compression Quality';
$l['setting_flp_quality_desc'] = 'Quality of the compressed image.';

$l['setting_flp_avail'] = 'Availability';
$l['setting_flp_avail_desc'] = 'Make the avatars available in the following areas.<br /><i>Scope:</i> ¹ First Avatar only, ² Last Avatar Only, ³ First & Last Avatar';

$l['flp_quality_asis'] = 'As Is (No Compression)';
$l['flp_quality_default'] = 'Optimal (Default zlib)';
$l['flp_quality_good'] = 'Good (Higher size)';
$l['flp_quality_medium'] = 'Medium';
$l['flp_quality_lossy'] = 'Lossy (Lower size)';

$l['setting_flp_cdnlink'] = 'CDN Link';
$l['setting_flp_cdnlink_desc'] = 'If you are using a CDN or remote asset mirror service provide the link. This will be used to fetch avatars from instead of local asset_url, if set.<br />(Trailing \'/\' doens\'t matter.)';