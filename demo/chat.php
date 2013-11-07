<?php
# include the ably library
require_once '../config.php';
require_once '../lib/ably.php';

# private api key
$api_key = ABLY_KEY;
$channel_name = isset($_REQUEST['channel']) ? $_REQUEST['channel'] : 'chat2';
$event_name = isset($_REQUEST['event']) ? $_REQUEST['event'] : 'guest';
$host = defined('ABLY_HOST') ? ABLY_HOST : null;
$ws_host = defined('ABLY_WS_HOST') ? ABLY_WS_HOST : null;

# instantiate Ably
$app = new AblyRest(array(
    'host' => $host,
    'key'  => $api_key,
    'debug' => 'log'
));
//$app->authorise();
$channel0 = $app->channel($channel_name);
$messages = array();

# publish something
if (!empty($_POST)) {
    $channel0->publish($event_name, json_encode(array('handle' => $_POST['handle'], 'message' => $_POST['message'])));
    die();
} else {
    $messages = $channel0->history(array('direction' => 'backwards'));
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
    <p>Say something: <input type="text" name="message" size="50"> <button id="rest" type="button">Send REST</button> <button id="realtime" type="button">Send REALTIME</button></p>
</form>

<div class="chat-window">
    <div class="chat-window-content">
        <ul id="message_pool">
            <?php for ($i=0; $i<count($messages); $i++): ?>
            <?php if (property_exists($messages[$i], 'data')) : $message = json_decode($messages[$i]->data); ?>
            <li><b class="handle"><?= $message->handle ?>:</b> <?= $message->message ?></li>
            <?php endif; endfor; ?>
        </ul>
    </div>
</div>

<script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
<script type="text/javascript">
    (function($) {
        var channel = {};

        $.getScript('lib/ably.min.js', function() {
            var ably = new Ably.Realtime({
                        key: '<?= $api_key ?>',
                        tls: true,
                        log: {level:4}
                    });

            channel = ably.channels.get('<?= $channel_name ?>');

            channel.subscribe('<?= $event_name ?>', function(response) {
                var data = JSON.parse(response.data);
                $('#message_pool').prepend('<li><b class="handle">'+ data.handle +':</b> '+ data.message +'</li>');
            });
        });

        function sendMessage(mode) {
            var $form = $('#message_form'),
                broadcast = true,
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
                if (mode === 'realtime') {
                    channel.publish('<?= $event_name ?>', JSON.stringify({ handle: $handle.val(), message: $message.val() }) );
                    $message.val('');
                } else {
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
            }
        }

        $('#rest').on('click', function() {
            sendMessage('rest');
            return false;
        });

        $('#realtime').on('click', function() {
            sendMessage('realtime');
            return false;
        });
    })(jQuery);
</script>

</body>
</html>