<?php

require dirname(__FILE__). '/tmhOAuth.php';
$setting = array(
  'consumer_key'    => '',
  'consumer_secret' => '',
  'user_token'      => '',
  'user_secret'     => '',
  'screen_name'     => '',
  'curl_ssl_verifypeer' => false,
);
//require dirname(__FILE__). '/setting.php';
$twitter = new tmhOAuth($setting);

$wait = 2;
while ($wait) {
  echo "Conntecting...\n";
  $twitter->streaming_request('POST', 'https://userstream.twitter.com/2/user.json', array(), 'callback');
  if     ($twitter->response['code'] == 200) $wait =  1;
  elseif ($twitter->response['code'] == 420) $wait *= 4;
  elseif ($twitter->response['code'] ==   0) $wait =  2;
  else $wait *= 2;
  if ($wait > 240) $wait = 240;
  echo "Disconnected! (Code: {$twitter->response['code']})\nRetry in $wait secs...";
  sleep($wait);
}

function callback($data, $length, $metrics) {
  global $twitter;
  $data = json_decode($data);
  
  if ($data->{'event'} == 'favorite') {
    $user     = $data->{'source'}->{'screen_name'};
    $username = $data->{'source'}->{'name'};
    $status   = $data->{'target_object'}->{'text'};
    $target   = $data->{'target'}->{'screen_name'};
    $targetn  = $data->{'target'}->{'name'};
    say("@${user} ($username) has favorited: @${target} ($targetn): $status");
  }
  elseif ($data->{'id_str'}) {
    $user     = $data->{'user'}->{'screen_name'};
    $username = $data->{'user'}->{'name'};
    $status   = $data->{'text'};
    $id       = $data->{'id_str'};
    $replyto  = $data->{'in_reply_to_screen_name'};
    $client   = preg_replace("/<[^>]+>/", '', $data->{'source'});
    if ($replyto == $twitter->config['screen_name']) {
      if (preg_match("/@{$replyto}\s*(\([^)]+\))/", $status, $matches)) {
        $count = substr_count($status, '(') + substr_count($status, '（') +1;
        $text = "@${user} ";
        $kaomoji = $matches[1];
        for ($i = 0; $i < $count; $i++) $text .= $kaomoji;
        $twitter->request('POST', $twitter->url('1/statuses/update'),array(
          'status' => $text,
          'in_reply_to_status_id' => $id,
        ));
        say("sent: $text");
      }
    }
    say("@${user} ($username): $status\n$id {$client}から");
  }
  
  elseif ($data->{'friends'}) say("Connected!");
  elseif (!$data) echo "Keeping connected\n";
  
}
function say($data) {
  $sep  = "\n----\n";
  echo mb_convert_encoding($data. $sep, 'SJIS', 'UTF8');
}

?>