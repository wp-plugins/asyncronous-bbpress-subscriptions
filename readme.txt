=== AsynCRONous bbPress Subscriptions ===
Contributors: mechter
Donate link: http://www.markusechterhoff.com/donation/
Tags: bbpress, email, notifications, subscription, cron, wp cron, asynchronous
Requires at least: 3.6
Tested up to: 4.2.1
Stable tag: 1.1
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Email notifications done right. No BCC lists, no added page load time.

== Description ==

Per default, bbPress is sending subscription notification emails as one email with a bunch of BCCs. There are various reasons why it would make more sense to send individual emails. This plugin does that, quietly in the background via WP cron, without slowing down page load times.

= Defaults =

If you don't [customize](https://wordpress.org/plugins/asyncronous-bbpress-subscriptions/customization/) this plugin, this is what you'll get:

* Sends mails from `"MyBlog <admin@MyBlog.foo>"` (with your Blog's name and admin email)
* Sends mail to `"Markus <markus@example.com>"` (with the name being the user's display name on the forums, not their username)
* Subject and Message are the bbPress defaults, use the filters below to make them your own.

== Frequently Asked Questions ==

= No emails are being sent =
If other WP emails work normally try adding `define('ALTERNATE_WP_CRON', true);` to your `wp-config.php`

= Can I use real cron instead of WP cron? =
Yes. Add `define('DISABLE_WP_CRON', true);` to your `wp-config.php` and have a real cron job execute e.g. `wget -q -O - http://your.blog.example.com/wp-cron.php >/dev/null 2>&1`

== Changelog ==

= 1.1 =

* changed filter: `abbps_to` has new signature `abbps_to( $to, $post_author_user_id )` where $to is `array( 'name' => '', 'address' => '' )`
* new filter: `abbps_recipients` filters array of recipients just before sending so you can e.g. remove blacklisted emails just in time

= 1.0 =

* initial release

== Customization ==

= Available filters =

	abbps_to( $to, $post_author_user_id )
	abbps_from( $from, $from_name, $from_address )
	abbps_topic_subject( $subject, $forum_id, $topic_id )
	abbps_topic_message( $message, $forum_id, $topic_id )
	abbps_reply_subject( $subject, $forum_id, $topic_id, $reply_id )
	abbps_reply_message( $message, $forum_id, $topic_id, $reply_id )
	abbps_recipients( $recipients )

= Helpful Snippets =

Here are some pointers to get the data you might want in your notifications:

	$blog_name = get_bloginfo( 'name' );

	$forum_title = bbp_get_forum_title( $forum_id );

	$topic_author_user_id = bbp_get_topic_author_id( $topic_id );
	$topic_author_display_name = bbp_get_topic_author_display_name( $topic_id );
	$topic_title = strip_tags( bbp_get_topic_title( $topic_id ) );
	$topic_content = strip_tags( bbp_get_topic_content( $topic_id ) );
	$topic_url = get_permalink( $topic_id );

	$reply_author_user_id = bbp_get_reply_author_id( $reply_id );
	$reply_author_display_name = bbp_get_topic_author_display_name( $reply_id );
	$reply_content = strip_tags( bbp_get_reply_content( $reply_id ) );
	$reply_url = bbp_get_reply_url( $reply_id ); // note that it's not get_permalink()

= Example =

To have a nice subject line for new topic notifications, add this to your theme's functions.php:

	add_filter( 'abbps_topic_subject', function( $subject, $forum_id, $topic_id ) {
		$blog_name = get_bloginfo( 'name' );
		$topic_author_display_name = bbp_get_topic_author_display_name( $topic_id );
		$topic_title = strip_tags( bbp_get_topic_title( $topic_id ) );
		return "[$blog_name] $topic_author_display_name created a new topic: $topic_title";
	}, 10, 3); // first is priority (10 is default and just fine), second is number of arguments your filter expects
