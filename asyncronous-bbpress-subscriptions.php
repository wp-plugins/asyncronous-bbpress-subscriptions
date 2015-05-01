<?php
/*
Plugin Name: AsynCRONous bbPress Subscriptions
Description: Email notifications done right. No BCC lists, no added page load time.
Plugin URI: http://wordpress.org/extend/plugins/asyncronous-bbpress-subscriptions/
Author: Markus Echterhoff
Author URI: http://www.markusechterhoff.com
Version: 1.1
License: GPLv3 or later
*/

remove_action( 'bbp_new_topic', 'bbp_notify_forum_subscribers' );
add_action( 'bbp_new_topic', 'abbps_notify_forum_subscribers', 11, 4 );

remove_action( 'bbp_new_reply', 'bbp_notify_subscribers' );
add_action( 'bbp_new_reply', 'abbps_notify_subscribers', 11, 5 );

class ABBPSEmail {

	public $subject;
	public $message;
	public $headers;
	public $recipients = array();
	
	public function __construct() {
		$from_name = get_bloginfo( 'name' );
		$from_address = get_bloginfo('admin_email');
		$this->headers = 'From: ' . apply_filters( 'abbps_from', "$from_name <$from_address>", $from_name, $from_address ) . "\r\n" ; // string version $headers needs proper line ending, see wp codex on wp_mail()
	}
	
	public function add_recipient( $user_id ) {
		$user = get_userdata( $user_id );
		$to_name = $user->display_name;
		$to_address = $user->user_email;
		$this->recipients[]= apply_filters( 'abbps_to', array( 'name' => $to_name, 'address' => $to_address ), $to_name, $to_address, $user_id );
	}
	
	public function schedule_sending() {
		wp_schedule_single_event( time(), 'abbps_sending_time', array( $this ) );
	}
}

class ABBPSNewTopic extends ABBPSEmail {

	public function __construct( $forum_id, $topic_id, $subject, $message ) {
		parent::__construct();
		
		$this->subject = apply_filters( 'abbps_topic_subject', $subject, $forum_id, $topic_id );
		$this->message = apply_filters( 'abbps_topic_message', $message, $forum_id, $topic_id );
	}
}

class ABBPSNewReply extends ABBPSEmail {

	public function __construct( $forum_id, $topic_id, $reply_id, $subject, $message ) {
		parent::__construct();

		$this->subject = apply_filters( 'abbps_reply_subject', $subject, $forum_id, $topic_id, $reply_id );
		$this->message = apply_filters( 'abbps_reply_message', $message, $forum_id, $topic_id, $reply_id );
	}
}

add_action( 'abbps_sending_time', 'abbps_mail', 10, 1 );
function abbps_mail( $email ) {
	$filtered_recipients = apply_filters( 'abbps_recipients', $email->recipients );
	foreach ( $filtered_recipients as $to ) {
		wp_mail( ( $to['name'] ? "{$to['name']} <{$to['address']}>" : $to['address'] ), $email->subject, $email->message, $email->headers );
	}
}
	
/*
 * Original code from bbpress/includes/common/functions.php with unused stuff commented out and only minimal stuff added
 */

function abbps_notify_forum_subscribers( $topic_id = 0, $forum_id = 0, $anonymous_data = false, $topic_author = 0 ) {

	// Bail if subscriptions are turned off
	if ( !bbp_is_subscriptions_active() ) {
		return false;
	}

	/** Validation ************************************************************/

	$topic_id = bbp_get_topic_id( $topic_id );
	$forum_id = bbp_get_forum_id( $forum_id );

	/** Topic *****************************************************************/

	// Bail if topic is not published
	if ( ! bbp_is_topic_published( $topic_id ) ) {
		return false;
	}

	// Poster name
	$topic_author_name = bbp_get_topic_author_display_name( $topic_id );

	/** Mail ******************************************************************/

	// Remove filters from reply content and topic title to prevent content
	// from being encoded with HTML entities, wrapped in paragraph tags, etc...
	remove_all_filters( 'bbp_get_topic_content' );
	remove_all_filters( 'bbp_get_topic_title'   );

	// Strip tags from text and setup mail data
	$topic_title   = strip_tags( bbp_get_topic_title( $topic_id ) );
	$topic_content = strip_tags( bbp_get_topic_content( $topic_id ) );
	$topic_url     = get_permalink( $topic_id );
	$blog_name     = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	//$do_not_reply  = '<noreply@' . ltrim( get_home_url(), '^(http|https)://' ) . '>';

	// For plugins to filter messages per reply/topic/user
	$message = sprintf( __( '%1$s wrote:

%2$s

Topic Link: %3$s

-----------

You are receiving this email because you subscribed to a forum.

Login and visit the topic to unsubscribe from these emails.', 'bbpress' ),

		$topic_author_name,
		$topic_content,
		$topic_url
	);

	$message = apply_filters( 'bbp_forum_subscription_mail_message', $message, $topic_id, $forum_id, $user_id );
	if ( empty( $message ) ) {
		return;
	}

	// For plugins to filter titles per reply/topic/user
	$subject = apply_filters( 'bbp_forum_subscription_mail_title', '[' . $blog_name . '] ' . $topic_title, $topic_id, $forum_id, $user_id );
	if ( empty( $subject ) ) {
		return;
	}

	/** User ******************************************************************/

	// Array to hold BCC's
	//$headers = array();

	// Setup the From header
	//$headers[] = 'From: ' . get_bloginfo( 'name' ) . ' ' . $do_not_reply;
	
	// Get topic subscribers and bail if empty
	$user_ids = bbp_get_forum_subscribers( $forum_id, true );
	if ( empty( $user_ids ) ) {
		return false;
	}

	$email = new ABBPSNewTopic( $forum_id, $topic_id, $subject, $message );

	// Loop through users
	foreach ( (array) $user_ids as $user_id ) {

		// Don't send notifications to the person who made the post
		if ( !empty( $topic_author ) && (int) $user_id === (int) $topic_author ) {
			continue;
		}

		// Get email address of subscribed user
		//$headers[] = 'Bcc: ' . get_userdata( $user_id )->user_email;
		$email->add_recipient( $user_id );
	}

	/** Send it ***************************************************************/

	// Custom headers
	//$headers = apply_filters( 'bbp_subscription_mail_headers', $headers );

	do_action( 'bbp_pre_notify_forum_subscribers', $topic_id, $forum_id, $user_ids );

	// Send notification email
	//wp_mail( $do_not_reply, $subject, $message, $headers );
	$email->schedule_sending();

	do_action( 'bbp_post_notify_forum_subscribers', $topic_id, $forum_id, $user_ids );

	return true;
}


function abbps_notify_subscribers( $reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = false, $reply_author = 0 ) {

	// Bail if subscriptions are turned off
	if ( !bbp_is_subscriptions_active() ) {
		return false;
	}

	/** Validation ************************************************************/

	$reply_id = bbp_get_reply_id( $reply_id );
	$topic_id = bbp_get_topic_id( $topic_id );
	$forum_id = bbp_get_forum_id( $forum_id );

	/** Topic *****************************************************************/

	// Bail if topic is not published
	if ( !bbp_is_topic_published( $topic_id ) ) {
		return false;
	}

	/** Reply *****************************************************************/

	// Bail if reply is not published
	if ( !bbp_is_reply_published( $reply_id ) ) {
		return false;
	}

	// Poster name
	$reply_author_name = bbp_get_reply_author_display_name( $reply_id );

	/** Mail ******************************************************************/

	// Remove filters from reply content and topic title to prevent content
	// from being encoded with HTML entities, wrapped in paragraph tags, etc...
	remove_all_filters( 'bbp_get_reply_content' );
	remove_all_filters( 'bbp_get_topic_title'   );

	// Strip tags from text and setup mail data
	$topic_title   = strip_tags( bbp_get_topic_title( $topic_id ) );
	$reply_content = strip_tags( bbp_get_reply_content( $reply_id ) );
	$reply_url     = bbp_get_reply_url( $reply_id );
	$blog_name     = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	//$do_not_reply  = '<noreply@' . ltrim( get_home_url(), '^(http|https)://' ) . '>';

	// For plugins to filter messages per reply/topic/user
	$message = sprintf( __( '%1$s wrote:

%2$s

Post Link: %3$s

-----------

You are receiving this email because you subscribed to a forum topic.

Login and visit the topic to unsubscribe from these emails.', 'bbpress' ),

		$reply_author_name,
		$reply_content,
		$reply_url
	);

	$message = apply_filters( 'bbp_subscription_mail_message', $message, $reply_id, $topic_id );
	if ( empty( $message ) ) {
		return;
	}

	// For plugins to filter titles per reply/topic/user
	$subject = apply_filters( 'bbp_subscription_mail_title', '[' . $blog_name . '] ' . $topic_title, $reply_id, $topic_id );
	if ( empty( $subject ) ) {
		return;
	}

	/** Users *****************************************************************/

	// Array to hold BCC's
	//$headers = array();

	// Setup the From header
	//$headers[] = 'From: ' . get_bloginfo( 'name' ) . ' ' . $do_not_reply;
	
	// Get topic subscribers and bail if empty
	$user_ids = bbp_get_topic_subscribers( $topic_id, true );
	if ( empty( $user_ids ) ) {
		return false;
	}
	
	$email = new ABBPSNewReply( $forum_id, $topic_id, $reply_id, $subject, $message );

	// Loop through users
	foreach ( (array) $user_ids as $user_id ) {

		// Don't send notifications to the person who made the post
		if ( !empty( $reply_author ) && (int) $user_id === (int) $reply_author ) {
			continue;
		}

		// Get email address of subscribed user
		//$headers[] = 'Bcc: ' . get_userdata( $user_id )->user_email;
		$email->add_recipient( $user_id );
	}

	/** Send it ***************************************************************/

	// Custom headers
	//$headers = apply_filters( 'bbp_subscription_mail_headers', $headers );

	do_action( 'bbp_pre_notify_subscribers', $reply_id, $topic_id, $user_ids );

	// Send notification email
	//wp_mail( $do_not_reply, $subject, $message, $headers );
	$email->schedule_sending();

	do_action( 'bbp_post_notify_subscribers', $reply_id, $topic_id, $user_ids );

	return true;
}

?>
