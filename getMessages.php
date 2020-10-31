<?php
function getDiscordGuildChannelList($token)
{
    $header = array(
        "Content-Type: application/x-www-form-urlencoded",
        "Content-Length: 0",
        "Authorization: Bot " . $token,
        "User-Agent: DiscordBot (https://jaoafa.com, v0.0.1)"
    );

    $context = array(
        "http" => array(
            "method"  => "GET",
            "header"  => implode("\r\n", $header)
        )
    );
    $context = stream_context_create($context);
    $contents = file_get_contents("https://discordapp.com/api/guilds/597378876556967936/channels", false, $context);
    $json = json_decode($contents, true);
    return $json;
}
function searchChannel($token, $channelid)
{
    $channelList = getDiscordGuildChannelList($token);
    foreach ($channelList as $channel) {
        if ($channel["id"] == $channelid) {
            return $channel;
        }
    }
    return null;
}
function getChannelMessages($token, $channelid, $before = null, $after = null, $limit = 100)
{
    $header = array(
        "Content-Type: application/x-www-form-urlencoded",
        "Content-Length: 0",
        "Authorization: Bot " . $token,
        "User-Agent: DiscordBot (https://jaoafa.com, v0.0.1)"
    );

    $parameters = [];
    if ($before != null) {
        $parameters["before"] = $before;
    }
    if ($after != null) {
        $parameters["after"] = $after;
    }
    if ($limit != null) {
        $parameters["limit"] = $limit;
    }

    $parameter = http_build_query($parameters, "", "&");

    $context = array(
        "http" => array(
            "method"  => "GET",
            "header"  => implode("\r\n", $header)
        )
    );
    $context = stream_context_create($context);
    $contents = file_get_contents("https://discord.com/api/channels/" . $channelid . "/messages?" . $parameter, false, $context);
    $json = json_decode($contents, true);
    return $json;
}

function downloadUserIcon($token, $userid, $discriminator, $userIconUrl = null)
{
    if(file_exists(__DIR__ . "/usericon/" . $userid . ".png") || file_exists(__DIR__ . "/usericon/" . $userid . ".gif")){
        return;
    }
    $header = array(
        "Content-Length: 0",
        "Authorization: Bot " . $token,
        "User-Agent: DiscordBot (https://jaoafa.com, v0.0.1)"
    );

    $context = array(
        "http" => array(
            "method"  => "GET",
            "header"  => implode("\r\n", $header)
        )
    );
    if ($userIconUrl == null) {
        $userIconUrl = "https://cdn.discordapp.com/embed/avatars/" . ($discriminator % 5) . ".png";
    }
    file_put_contents(__DIR__ . "/usericon/" . $userid . "." . substr($userIconUrl, -3), file_get_contents($userIconUrl, false, stream_context_create($context)));
}

if (file_exists(__DIR__ . "/config.json")) {
    $config = json_decode(file_get_contents(__DIR__ . "/config.json"), true);
    $token = $config["token"];
} elseif (getenv("DISCORD_TOKEN") != false) {
    $token = getenv("DISCORD_TOKEN");
} else {
    echo "Discord token is not defined.";
    exit(1);
}

if (!file_exists(__DIR__ . "/usericon/")) {
    mkdir(__DIR__ . "/usericon/", 0755, true);
}

if (!file_exists(__DIR__ . "/channels/")) {
    mkdir(__DIR__ . "/channels/", 0755, true);
}

if(!isset($argv[1])){
    echo "Discord channel id is not defined.";
    exit(1);
}

$channel_id = $argv[1];
$channel = searchChannel($token, $channel_id);
$channel_name = $channel["name"];

$channelMessages = [];
$after = $channel_id;
while (true) {
    $channelMsgs = getChannelMessages($token, $channel_id, null, $after);
    if (count($channelMsgs) == 0) {
        break;
    }
    krsort($channelMsgs);
    foreach ($channelMsgs as $msg) {
        $msg_id = $msg["id"];
        $msg_content = $msg["content"];
        $msg_content = htmlspecialchars($msg_content, ENT_QUOTES, "UTF-8");
        $msg_content = nl2br($msg_content);
        $msg_content = preg_replace("/__(.+)__/", "<u>$1</u>", $msg_content);
        $msg_content = preg_replace("/\*\*(.+)\*\*/", "<b>$1</b>", $msg_content);
        $msg_content = preg_replace("/~~(.+)~~/", "<s>$1</s>", $msg_content);
        $msg_content = preg_replace("/```(.+)```/", "\n<code>$1</code>\n", $msg_content);
        $msg_content = preg_replace("/``(.+)``/", "<code>$1</code>", $msg_content);
        $msg_content = preg_replace("/`(.+)`/", "<code>$1</code>", $msg_content);
        $msg_timestamp = $msg["timestamp"];
        $msg_timestamp = strtotime($msg_timestamp);
        $msg_date = date("Y/m/d H:i:s", $msg_timestamp);
        $msg_userid = $msg["author"]["id"];
        $msg_username = $msg["author"]["username"];
        $msg_discriminator = $msg["author"]["discriminator"];
        $msg_avatar = $msg["author"]["avatar"];

        $userIconUrl = null;
        if ($msg_avatar != null) {
            $userIconUrl = "https://cdn.discordapp.com/avatars/" . $msg_userid . "/" . $msg_avatar . "." . (substr($msg_avatar, 0, 2) == "a_" ? "gif" : "png");
        }
        downloadUserIcon($token, $msg_userid, $msg_discriminator, $userIconUrl);

        $channelMessages[] = [
            "id" => $msg_id,
            "content" => $msg_content,
            "date" => $msg_date,
            "userid" => $msg_userid,
            "username" => $msg_username,
            "discriminator" => $msg_discriminator,
            "avatar_type" => substr($msg_avatar, 0, 2) == "a_" ? "gif" : "png"
        ];
        $after = $msg_id;
    }
}

file_put_contents(
    __DIR__ . "/channels/" . $channel_id . ".json",
    json_encode(
        [
            "name" => $channel_name,
            "messages" => $channelMessages,
        ]
    )
);