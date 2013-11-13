<?php
# include the ably library
require_once '../config.php';
require_once '../lib/ably.php';

# private api key
$api_key = ABLY_KEY;
$channel_name = isset($_REQUEST['channel']) ? $_REQUEST['channel'] : 'chat2';
$event_name = isset($_REQUEST['event']) ? $_REQUEST['event'] : 'guest';

# instantiate Ably
$app = new AblyRest(array(
    'key'  => $api_key,
    'debug' => 'log'
));

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
    <meta name="HandheldFriendly" content="True">
    <meta name="MobileOptimized" content="320">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1; user-scalable=no">
    <title>Simple Chat</title>
    <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.0.0/css/bootstrap.min.css">
    <style>
        body { padding: 10px; overflow: hidden; background: url(//d6i46dwqrtafp.cloudfront.net/images/bg/carbon_fibre.png) }
        .chat-window { overflow: hidden; border-top: 1px solid #e1e1e1; position: relative; }
        .chat-window-content { overflow: auto; color: #888; height: 500px; }
        .chat-window-content > ul { list-style: none; margin: 25px 0 50px; padding: 0; }
        /*.chat-window-content > ul > li { border-bottom: 1px solid #e1e1e1; padding: 2px 10px }*/
        .chat-window-shadow { position: absolute; z-index: 100; height: 50px; width: 100%; }
        .chat-window-shadow-top { top: 0; background-image: -webkit-linear-gradient(top, rgba(255,255,255, 1), rgba(255,255,255, 0)) }
        .chat-window-shadow-bottom { bottom: 0; background-image: -webkit-linear-gradient(bottom, rgba(255,255,255, 1), rgba(255,255,255, 0)) }
        .handle { color: #2f91ff; font-weight: bold }
        time { float: right; color: #ccc; }
    </style>
</head>
<body>

<div class="panel panel-default">
    <div class="panel-heading">
        <h1 class="panel-title">Let's Chat</h1>
    </div>
    <div class="panel-body">
        <form id="message_form" method="post" action="/demo/chat.php" class="form-inline" role="form">
            <input type="hidden" name="channel" value="<?= $channel_name ?>">
            <input type="hidden" name="event" value="<?= $event_name ?>">
            <div class="form-group">
                <input type="text" name="handle" class="form-control input-sm" placeholder="Your handle">
            </div>
            <div class="form-group">
                <input type="text" name="message" class="form-control input-sm" placeholder="Say something">
            </div>
            <button id="rest" type="button" class="btn btn-default btn-sm">Send REST</button>
            <button id="realtime" type="button" class="btn btn-default btn-sm">Send REALTIME</button>
        </form>
    </div>
    <div class="chat-window list-group">
        <div class="chat-window-content">
            <ul id="message_pool">
                <?php $date_format = 'D jS F, Y'; $stamp = date($date_format) ?>
                <?php for ($i=0; $i<count($messages); $i++): ?>
                    <?php if (property_exists($messages[$i], 'data')) : $message = json_decode($messages[$i]->data); $timestamp = strlen($messages[$i]->timestamp) > 10 ? intval($messages[$i]->timestamp)/1000 : $messages[$i]->timestamp; $day = gmdate($date_format, $timestamp); ?>
                        <?php if ($stamp != $day) : ?>
                            <li class="list-group-item"><h2 class="h4"><?= $day ?></h2></li>
                            <?php $stamp = $day; endif; ?>
                        <li class="list-group-item"><time><?= gmdate('h:i a', $timestamp) ?></time> <b class="handle"><?= $message->handle ?>:</b> <?= $message->message ?></li>
                    <?php endif; endfor; ?>
            </ul>
            <div class="chat-window-shadow chat-window-shadow-top"></div>
            <div class="chat-window-shadow chat-window-shadow-bottom"></div>
        </div>
    </div>
</div>

<script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
<script src="lib/ably.min.js"></script>
<script type="text/javascript">
    (function($) {

        // adjust chat window height
        var $chatWindowContent = $('.chat-window-content');

        $(window).on('resize', function() {
            $chatWindowContent.height($(this).height() - $chatWindowContent.offset().top - 10);
        }).resize();

        var ably = new Ably.Realtime({
            key: '<?= $api_key ?>',
            tls: true,
            log: {level:4}
        });

        var channel = ably.channels.get('<?= $channel_name ?>');

        channel.subscribe('<?= $event_name ?>', function(response) {
            var data = JSON.parse(response.data);
            var timestamp = response.timestamp
            var d = new Date( timestamp.toString().length > 10 ? timestamp : timestamp*1000 );
            var hours = d.getUTCHours();
            var ampm = hours > 12 ? ' pm' : ' am';
            hours = hours % 12;
            hours = hours === 0 ? 12 : hours;
            var minutes = ('0'+d.getUTCMinutes()).substr(-2);
            var post_time = [hours, minutes].join(':') + ampm;
            $('#message_pool').prepend('<li class="list-group-item"><span class="label label-danger">js</span> <time>'+ post_time +'</time> <b class="handle">'+ data.handle +':</b> '+ data.message +'</li>');
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