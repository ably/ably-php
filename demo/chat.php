<?php
# include the ably library
require_once '../config.php';
require_once '../lib/ably.php';

# private api key
$api_key = ABLY_KEY;
$channel_name = isset($_REQUEST['channel']) ? $_REQUEST['channel'] : 'chat';
$event_name = isset($_REQUEST['event']) ? $_REQUEST['event'] : 'guest';
$host = ABLY_HOST;
$ws_host = ABLY_WS_HOST;

# instantiate Ably
$app = new Ably(array(
    'host' => $host,
    'key'  => $api_key,
    'debug' => 'log'
));

# TODO : demo how to use the auth token for real-time connections
# $authToken = $app->token;

# publish something
if (!empty($_POST)) {
    $channel0 = $app->channel($channel_name);
    $channel0->publish($event_name, json_encode(array('handle' => $_POST['handle'], 'message' => $_POST['message'])));
    die();
}

?>
<!DOCTYPE HTML>
<html lang="en-US">
<head>
    <meta charset="UTF-8">
    <title>Simple Chat</title>
    <style>
        body { font-family: sans-serif }
        .chat-window { overflow: hidden; min-width: 320px; width: 100%; border: 2px inset #e1e1e1 }
        .chat-window-content { overflow: auto; font-size: 12px; line-height: 1.4; color: #888; height: 300px; }
        .chat-window-content > ul { list-style: none; margin: 0; padding: 0 }
        .chat-window-content > ul > li { border-bottom: 1px solid #e1e1e1; padding: 2px 10px }
        .handle { color: #2f91ff; font-weight: bold }
    </style>
</head>
<body>

<h1>Let's Chat</h1>

<form id="message_form" method="post" action="/demo/chat.php">
    <input type="hidden" name="channel" value="<?= $channel_name ?>">
    <input type="hidden" name="event" value="<?= $event_name ?>">
    <p>Your handle: <input type="text" name="handle"></p>
    <p>Say something: <input type="text" name="message" size="50"> <button type="submit">Send</button></p>
</form>

<div class="chat-window">
    <div class="chat-window-content">
        <ul id="message_pool"></ul>
    </div>
</div>

<script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
<script type="text/javascript">
    (function($) {
        $.getScript('https://cdn.ably.io/lib/ably.js', function() {
            var ably = new Ably.Realtime({
                        key: '<?= $api_key ?>',
                        encrypted: true,
                        log: {level:4},
                        wsHost: '<?= $ws_host ?>'
                    }),
                    channel = ably.channels.get('<?= $channel_name ?>');

            channel.subscribe('<?= $event_name ?>', function(response) {
                var data = JSON.parse(response.data);
                $('#message_pool').append('<li><b class="handle">'+ data.handle +':</b> '+ data.message +'</li>');
            });
        });

        $('#message_form').on('submit', function(event) {
            var $form = $(this), broadcast = true,
                $handle = $('[name="handle"]', $form),
                $message = $('[name="message"]', $form);

            if ($.trim($handle.val()) === '') {
                alert('you must provide a handle');
                $handle.focus();
                broadcast = false;
            }

            if ($.trim($message.val()) === '') {
                alert('you must type a message');
                $message.focus();
                broadcast = false;
            }

            if (broadcast) {
                $.ajax({
                    url: $form[0].action,
                    data: $form.serialize(),
                    type: $form[0].method,
                    dataType: 'json',
                    complete: function() {
                        $message.val('');
                    }
                });
            }

            return false;
        });
    })(jQuery);
</script>

</body>
</html>