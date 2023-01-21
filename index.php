<?php
include('secret.php');

//setup
const SLACK_API_BASE = 'https://slack.com/api/';
const LOG_FILENAME = 'log.txt';

const ACTION_TYPES = [
	'UNIQUES' => 'emojicheck-uniques',
	'MISSING' => 'emojicheck-missing'
];



//check that it is a slack request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(400);
	die();
}

$body = $_POST['payload'];
if( empty($body) ){
	http_response_code(400);
	die();
}

$data = json_decode($body);
if( empty($data) || $data->type !== 'message_action' || $data->token !== SLACK_VERIFICATION_TOKEN ){
	http_response_code(400);
	die();
}



//send immediate output to allow continued processing; prevents error message from hitting Slack
ob_start();
$size = ob_get_length();
header("Content-Encoding: none");
header("Content-Length: {$size}");
header("Connection: close");
ob_end_flush();
@ob_flush();
flush();



//grab Action information
$actionType = $data->callback_id;
$channelID = $data->channel->id;
$ts = $data->message_ts;
$userID = $data->user->id;
$responseURL = $data->response_url;



//a little bit of DRY
function build_slack_url($endpoint, $args = []){
	$url = SLACK_API_BASE.$endpoint;
	$args['token'] = SLACK_TOKEN;
	if( !empty($args) ){
		$url .= '?'.http_build_query($args);
	}
	return $url;
}

function slack_get_request($endpoint, $args = [], $log = false){
	$response = file_get_contents(build_slack_url($endpoint, $args));
	$data = json_decode($response);

	if($log || !$data->ok) {
		error_log(date('c')."\n".$response."\n\n", 3, LOG_FILENAME);
	}

	if( !$data->ok ){
		http_response_code(500);
		die();
	}

	return $data;
}

function pluck_users($desiredUserIDs){
	global $usersByID;
	//grabs the value (full name) from the user dictionary
	return array_reduce($desiredUserIDs, function($carry, $userID) use ($usersByID) {
		if( isset($usersByID[$userID]) ){
			$carry[] = $usersByID[$userID];
		}
		return $carry;
	}, []);
}



//get reaction and user data from Slack
$reactionsData = slack_get_request('reactions.get', [
	'channel'=>$channelID,
	'timestamp'=>$ts,
	'full'=>'true'
]);
$reactions = $reactionsData->message->reactions;
if( empty($reactions) ){
	http_response_code(204);
	die();
}
$userIDsThatReacted = array_unique(array_merge(...array_column($reactions, 'users')));
if( empty($reactions) ){
	http_response_code(204);
	die();
}

$usersData = slack_get_request('users.list');
if( empty($usersData) ){
	http_response_code(500);
	die();
}



//prepare data for analysis
$usersByID = [];
foreach($usersData->members as $user){
	if( $user->is_bot || $user->deleted || $user->is_app_user ){
		continue;
	}
	$usersByID[$user->id] = $user->profile->real_name;
}

if( empty($userIDsThatReacted) || empty($usersByID) ){
	http_response_code(204);
	die();
}

if( $actionType === ACTION_TYPES['UNIQUES'] ){
	$people = pluck_users($userIDsThatReacted);
	$intro = 'The following *'.count($people).' people* have reacted:';
} else {
	$membersData = slack_get_request('conversations.members', [
		'channel'=>$channelID,
		'limit'=>200
	]);
	$memberUserIDs = $membersData->members;
	$userIDsWithoutReactions = array_diff($memberUserIDs, $userIDsThatReacted);
	$people = pluck_users($userIDsWithoutReactions);
	$intro = count($people) > 0 ? 'The following *'.count($people).' people* have not reacted yet:' : '_Everyone has reacted!_ :tada:';
}

sort($people);
$peopleListing = count($people) > 0 ? "\n\n• " . implode("\n• ", $people) : '';



//send ephemeral message to original user
$messageData = [
	'token' => SLACK_TOKEN,
	'channel' => $channelID,
	'user' => $userID,
	'text' => $intro . $peopleListing
];

$ch = curl_init(SLACK_API_BASE.'chat.postEphemeral');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($messageData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response);
if( !$data->ok ){
	http_response_code(500);
	die();
}



//send success code back to close out the Action request
http_response_code(200);
die();