<?php
use Ratchet\RFC6455\Messaging\Protocol\MessageInterface;
use Ratchet\RFC6455\Messaging\Protocol\FrameInterface;
use Ratchet\RFC6455\Messaging\Protocol\Frame;

require_once __DIR__ . "/../bootstrap.php";

$loop   = \React\EventLoop\Factory::create();

if ($argc > 1 && is_numeric($argv[1])) {
    echo "Setting test server to stop in " . $argv[1] . " seconds.\n";
    $loop->addTimer($argv[1], function () {
        exit;
    });
}

$socket = new \React\Socket\Server($loop);
$server = new \React\Http\Server($socket);

$encodingValidator = new \Ratchet\RFC6455\Encoding\Validator;
$closeFrameChecker = new \Ratchet\RFC6455\Messaging\Protocol\CloseFrameChecker;
$negotiator = new \Ratchet\RFC6455\Handshake\Negotiator($encodingValidator);

$uException = new \UnderflowException;

$server->on('request', function (\React\Http\Request $request, \React\Http\Response $response) use ($negotiator, $encodingValidator, $closeFrameChecker, $uException) {
    $psrRequest = new \GuzzleHttp\Psr7\Request($request->getMethod(), $request->getPath(), $request->getHeaders());

    $negotiatorResponse = $negotiator->handshake($psrRequest);

    $response->writeHead(
        $negotiatorResponse->getStatusCode(),
        array_merge(
            $negotiatorResponse->getHeaders(),
            ["Content-Length" => "0"]
        )
    );

    if ($negotiatorResponse->getStatusCode() !== 101) {
        $response->end();
        return;
    }

    $parser = new \Ratchet\RFC6455\Messaging\Streaming\MessageStreamer($encodingValidator, $closeFrameChecker, function(MessageInterface $message) use ($response) {
        $response->write($message->getContents());
    }, function(FrameInterface $frame) use ($response, &$parser) {
        switch ($frame->getOpCode()) {
            case Frame::OP_CLOSE:
                $response->end($frame->getContents());
                break;
            case Frame::OP_PING:
                $response->write($parser->newFrame($frame->getPayload(), true, Frame::OP_PONG)->getContents());
                break;
        }
    }, true, function() use ($uException) {
        return $uException;
    });

    $request->on('data', [$parser, 'onData']);
});

$socket->listen(9001, '0.0.0.0');
$loop->run();
