[![Xekko](http://xekko.co.uk/public/images/logo_xekko_color.png "Xekko Resources")](http://resources.xekko.co.uk "Xekko Resources")

## First / Last Post Avatar
This plugin allows you to generate avatar information for the first and/or last post for use on your forum's home page (index), forum display (forumdisplay), search results and thread page (showthread).

While this plugin generates the information it does not automatically create the image. It is up to you to decide how best to implement the avatar onto the page.

### Licence
Take note of the [Xekko MyNetwork Licence](http://xekko.co.uk/service-licence.html "Xekko MyNetwork Licence").

If you ignore it, at least don't try and pass this off as your own work. Please don't make it available for download on your site or other places on the web and direct users here or to the MyBB Mods site.

### Installing First / Last Post Avatar
Download this package via Github, MyBB Mods site or Xekko Resources. Upload all the files contained in the Upload folder to your forum's root directory.

Once uploaded, visit the Plugins page in your ACP and install the plugin. This plugin adds a cache record and a setting. To enable the avatar information to be generated, visit the Configuration tab in the ACP and then General Configuration settings. There is a new setting here with four checkboxes; choose which areas you would like to display avatars in. Finally, follow the instructions below to modify your theme templates to insert the avatars.

### Using First / Last Post Avatar
#### Overview
Avatar generation provides 4 pieces of information: the avatar URL, scaled dimensions, username and profile link. First post avatar information is available via *$flp_avatar*. Last post avatar information is available via *$flp_lastpost*.

##### Example data
	flp_avatar = array(
		'avatar' => 'http://myforum/images/default_avatar.gif',
		'dimensions' => "width='44' height='44'",
		'username' => 'My Username',
		'profile' => 'member.php?action=profile&uid=1'
	)

For avatars to show you must add the following codes to each template. You don't have to use these particular templates although they are probably the most suitable. This allows you to fully customize the structure and look and feel of the coding and avatar while also allowing you to split different styles between first and last post avatars.

#### On the Forum Index
Once enabled, avatar information will be generated for the last poster of the forum.

##### Example
In forumbit_depth2_forum_lastpost:

	<span><a href='{$forum['flp_lastpost']['profile']}' title='Lastpost by {$forum['flp_lastpost']['username']}'><img src='{$forum['flp_lastpost']['avatar']}' {$forum['flp_lastpost']['dimensions']} alt='' /></a></span>

#### On Forum Display
Once enabled, avatar information is generated for both the first and last poster of the forum.

##### Example
For first post avatars, in forumdisplay_announcements_announcement and forumdisplay_thread:

	<span><a href='{$flp_avatar['profile']}' title='Started by {$flp_avatar['username']}'><img src='{$flp_avatar['avatar']}' {$flp_avatar['dimensions']} alt='' /></a></span>

For last post avatars, in forumdisplay_thread:

	<span><a href='{$flp_lastpost['profile']}' title='Lastpost by {$flp_lastpost['username']}'><img src='{$flp_lastpost['avatar']}' {$flp_lastpost['dimensions']} alt='' /></a></span>

#### Search Results
Once enabled, avatar information is generated for both the first and last poster of the forum if the results are thread based and the author avatar information is generated if the results are post based (available via *$flp_avatar*).

##### Example
For thread and post based results, the first post avatar, in search_results_posts_post and search_results_threads_thread:

	<span><a href='{$flp_avatar['profile']}' title='Started by {$flp_avatar['username']}'><img src='{$flp_avatar['avatar']}' {$flp_avatar['dimensions']} alt='' /></a></span>

For thread based results, the last post avatar, in search_results_threads_thread:

	<span><a href='{$flp_lastpost['profile']}' title='Lastpost by {$flp_lastpost['username']}'><img src='{$flp_lastpost['avatar']}' {$flp_lastpost['dimensions']} alt='' /></a></span>

#### Show Thread
Once enabled, avatar information is available for the first poster of the thread on all thread pages.

##### Example
For first post avatar, in showthread:

	<span><a href='{$flp_avatar['profile']}' title='Started by {$flp_avatar['username']}'><img src='{$flp_avatar['avatar']}' {$flp_avatar['dimensions']} alt='' /></a></span>

You can also add support for a Started By notice:

	<span>Started by <a href='{$flp_avatar['profile']}' title='View {$flp_avatar['username']}'s Profile'>{$flp_avatar['username']}</a></span>

### Support
Please visit [Xekko Resources](http://resources.xekko.co.uk/forum-11.html "Visit Xekko Resources") for support.

#### Notes to Remember
* Don't try and add avatars into the templates where you haven't enabled them. For example, if there are no permissions for avatars in search results, don't modify the search templates.
* *dimensions* contains scaled information depending on the *postmaxavatarsize* setting. If you want a consistent square avatar, use a set width with the image:
	<img src='{$flp_avatar['avatar']}' width="44" height="44" alt='' />