<?php

include_once __DIR__ . '/class-sdkwrapper.php';
include_once __DIR__ . '/class-pmppost.php';

/**
 * Ajax search functionality
 *
 * @since 0.1
 */
function pmp_search() {
	check_ajax_referer('pmp_ajax_nonce', 'security');

	$sdk = new SDKWrapper();
	$opts = array(
		'profile' => 'story',
		'limit' => 10
	);

	if (isset($_POST['query'])) {
		$query = json_decode(stripslashes($_POST['query']), true);
		$opts = array_merge($opts, $query);
	}

	if (isset($opts['guid'])) {
		$guid = $opts['guid'];
		unset($opts['guid']);
		$result = $sdk->query2json('fetchDoc', $guid, $opts);
	} else
		$result = $sdk->query2json('queryDocs', $opts);

	if (!$result) {
		header("HTTP/1.0 404 Not Found");
		print json_encode(array(
			"message" => "No results found.",
			"success" => false
		));
	} else {
		print json_encode(array(
			"data" => $result,
			"success" => true
		));
	}
	wp_die();
}
add_action('wp_ajax_pmp_search', 'pmp_search');

/**
 * Ajax function to create a draft post based on PMP story
 *
 * @since 0.1
 */
function pmp_draft_post() {
	check_ajax_referer('pmp_ajax_nonce', 'security');
	if (!current_user_can('edit_posts')) {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	else {
		print json_encode(_pmp_ajax_create_post(true));
		wp_die();
	}
}
add_action('wp_ajax_pmp_draft_post', 'pmp_draft_post');

/**
 * Ajax function to publish a post based on PMP story
 *
 * @since 0.1
 */
function pmp_publish_post() {
	check_ajax_referer('pmp_ajax_nonce', 'security');
	if (!current_user_can('edit_posts')) {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	else {
		print json_encode(_pmp_ajax_create_post());
		wp_die();
	}
}
add_action('wp_ajax_pmp_publish_post', 'pmp_publish_post');

/**
 * Ajax function to create new group
 *
 * @since 0.2
 */
function pmp_create_group() {
	check_ajax_referer('pmp_ajax_nonce', 'security');

	$group = json_decode(stripslashes($_POST['group']));
	$doc = _pmp_create_doc('group', $group);

	print json_encode(array(
		"success" => true,
		"data" => SDKWrapper::prepFetchData($doc)
	));
	wp_die();
}
add_action('wp_ajax_pmp_create_group', 'pmp_create_group');

/**
 * Ajax function to modify an existing group
 *
 * @since 0.2
 */
function pmp_modify_group() {
	check_ajax_referer('pmp_ajax_nonce', 'security');

	$group = json_decode(stripslashes($_POST['group']));
	$doc = _pmp_modify_doc($group);

	print json_encode(array(
		"success" => true,
		"data" => SDKWrapper::prepFetchData($doc)
	));
	wp_die();
}
add_action('wp_ajax_pmp_modify_group', 'pmp_modify_group');

/**
 * Ajax function to the default PMP group
 *
 * @since 0.2
 */
function pmp_default_group() {
	check_ajax_referer('pmp_ajax_nonce', 'security');

	$group = json_decode(stripslashes($_POST['group']));

	update_option('pmp_default_group', $group->attributes->guid);

	print json_encode(array("success" => true));
	wp_die();
}
add_action('wp_ajax_pmp_default_group', 'pmp_default_group');

/**
 * Ajax function to save a group's users
 *
 * @since 0.2
 */
function pmp_save_users() {
	check_ajax_referer('pmp_ajax_nonce', 'security');

	$group_data = json_decode(stripslashes($_POST['data']));

	$sdk = new SDKWrapper();
	$group = $sdk->fetchDoc($group_data->group_guid);

	if (!empty($group_data->user_guids)) {
		$group->links->item = array();

		foreach ($group_data->user_guids as $user_guid) {
			$link_item = new \stdClass();
			$link_item->href = $sdk->href4guid($user_guid);
			$group->links->item[] = $link_item;
		}
	} else
		unset($group->links->item);

	$group->save();

	print json_encode(array(
		"success" => true,
		"data" => SDKWrapper::prepFetchData($group)
	));
	wp_die();
}
add_action('wp_ajax_pmp_save_users', 'pmp_save_users');

/**
 * Ajax functions to create a new series or property
 *
 * @since 0.2
 */
function pmp_create_collection() {
	check_ajax_referer('pmp_ajax_nonce', 'security');

	$collection = json_decode(stripslashes($_POST['collection']));
	$doc = _pmp_create_doc($_POST['profile'], $collection);

	print json_encode(array(
		"success" => true,
		"data" => SDKWrapper::prepFetchData($doc)
	));
	wp_die();
}
add_action('wp_ajax_pmp_create_collection', 'pmp_create_collection');

/**
 * Ajax function to modify a series or property
 *
 * @since 0.2
 */
function pmp_modify_collection() {
	check_ajax_referer('pmp_ajax_nonce', 'security');

	$collection = json_decode(stripslashes($_POST['collection']));
	$doc = _pmp_modify_doc($collection);

	print json_encode(array(
		"success" => true,
		"data" => SDKWrapper::prepFetchData($doc)
	));
	wp_die();
}
add_action('wp_ajax_pmp_modify_collection', 'pmp_modify_collection');

/**
 * Ajax function to set the default PMP series or property
 *
 * @since 0.2
 */
function pmp_default_collection() {
	check_ajax_referer('pmp_ajax_nonce', 'security');

	$collection = json_decode(stripslashes($_POST['collection']));

	update_option('pmp_default_' . $_POST['profile'], $collection->attributes->guid);

	print json_encode(array("success" => true));
	wp_die();
}
add_action('wp_ajax_pmp_default_collection', 'pmp_default_collection');

/**
 * Ajax function to save a search query for later use
 *
 * @since 0.3
 */
function pmp_save_query() {
	check_ajax_referer('pmp_ajax_nonce', 'security');

	$search_query = json_decode(stripslashes($_POST['data']));

	if (isset($search_query->options->search_id)) {
		$search_id = $search_query->options->search_id;
		unset($search_query->options->search_id);
	} else
		$search_id = null;

	$search_id = pmp_save_search_query($search_id, $search_query);

	if ($search_id >= 0) {
		print json_encode(array(
			"success" => true,
			"search_id" => $search_id
		));
	} else
		print json_encode(array("success" => false));

	wp_die();
}
add_action('wp_ajax_pmp_save_query', 'pmp_save_query');

/**
 * Ajax function to delete a saved search query
 *
 * @since 0.3
 */
function pmp_delete_saved_query() {
	check_ajax_referer('pmp_ajax_nonce', 'security');

	$data = json_decode(stripslashes($_POST['data']), true);

	$ret = pmp_delete_saved_query_by_id($data['search_id']);

	if ($ret >= 0)
		print json_encode(array("success" => true));
	else
		print json_encode(array("success" => false));

	wp_die();
}
add_action('wp_ajax_pmp_delete_saved_query', 'pmp_delete_saved_query');

/**
 * Ajax function returns data structure describing select menu for Group, Series, Propert for
 * a post
 *
 * @since 0.3
 */
function pmp_get_select_options() {
	check_ajax_referer('pmp_ajax_nonce', 'security');

	$data = json_decode(stripslashes($_POST['data']), true);

	$post = get_post($data['post_id']);
	$type = $data['type'];

	$ret = _pmp_select_for_post($post, $type);
	print json_encode(array_merge(array("success" => true), $ret));

	wp_die();
}
add_action('wp_ajax_pmp_get_select_options', 'pmp_get_select_options');

/* Helper functions */
function _pmp_create_doc($type, $data) {
	$sdk = new SDKWrapper();

	if (!empty($data->attributes->tags))
		$data->attributes->tags = SDKWrapper::commas2array($data->attributes->tags);

	$doc = $sdk->newDoc($type, $data);
	$doc->save();

	return $doc;
}

function _pmp_modify_doc($data) {
	$sdk = new SDKWrapper();
	$doc = $sdk->fetchDoc($data->attributes->guid);

	if (!empty($data->attributes->tags))
		$data->attributes->tags = SDKWrapper::commas2array($data->attributes->tags);

	$doc->attributes = (object) array_merge((array) $doc->attributes, (array) $data->attributes);
	$doc->save();

	return $doc;
}

function _pmp_ajax_create_post($is_draft=false) {
	$sdk = new SDKWrapper();

	// make sure we don't search for a blank string
	$guid = empty($_POST['pmp_guid']) ? 'nothing' : $_POST['pmp_guid'];
	$doc = $sdk->fetchDoc($guid);
	if (empty($doc)) {
		return array(
			'success' => false,
			'message' => "Cannot find PMP document {$guid}",
		);
	}
	else {
		$syncer = PmpPost::fromDoc($doc);

		// pull from PMP
		if ($syncer->pull()) {
			return array(
				'success' => true,
				'data' => array(
					'edit_url' => html_entity_decode(get_edit_post_link($syncer->post->ID)),
					'post_id' => $syncer->post->ID,
				),
			);
		}
		else {
			return array(
				'success' => false,
				'message' => "Error: unable to pull PMP document {$guid}",
			);
		}
	}
}

/**
 * Builds a data structure that describes a select menu for the post based on the $type
 *
 * @param $type (string) The document option to create a select menu for
 * (i.e., 'group', 'property' or 'series').
 * @since 0.3
 */
function _pmp_select_for_post($post, $type) {
	$ret = array(
		'default_guid' => get_option('pmp_default_' . $type, false),
		'type' => $type
	);

	$sdk = new SDKWrapper();
	$pmp_things = $sdk->query2json('queryDocs', array(
		'profile' => $type,
		'writeable' => 'true',
		'limit' => 9999
	));

	$override = pmp_get_collection_override_value($post, $type);
	$options = array();

	// Pad the options with an empty value
	$options[] = array(
		'selected' => selected($override, false, false),
		'guid' => '',
		'title' => '--- No ' . $type . ' ---'
	);

	if (!empty($pmp_things['items'])) {
		foreach ($pmp_things['items'] as $thing) {
			if (!empty($override))
				$selected = selected($override, $thing['attributes']['guid'], false);

			$option = array(
				'selected' => (isset($selected))? $selected : '',
				'guid' => $thing['attributes']['guid'],
				'title' => $thing['attributes']['title']
			);
			$options[] = $option;
		}
	}

	$ret['options'] = $options;
	return $ret;
}
