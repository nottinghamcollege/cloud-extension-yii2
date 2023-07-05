<?php

namespace craft\cloud\runtime\event;

use Bref\Context\Context;
use Bref\Event\Handler;
use Bref\Event\Http\HttpRequestEvent;
use Bref\Event\InvalidLambdaEvent;
use Bref\FpmRuntime\FastCgi\FastCgiCommunicationFailed;
use Bref\FpmRuntime\FastCgi\Timeout;
use Bref\FpmRuntime\FpmHandler;
use RuntimeException;

class EventHandler implements Handler
{
    private FpmHandler $fpmHandler;

    public function __construct(FpmHandler $fpmHandler)
    {
        $this->fpmHandler = $fpmHandler;
    }

    /**
     * @throws InvalidLambdaEvent
     * @throws Timeout
     * @throws FastCgiCommunicationFailed
     * @throws \JsonException
     */
    public function handle(mixed $event, Context $context)
    {
        // See https://bref.sh/docs/runtimes/http.html#cold-starts
        if (isset($event['warmer']) && $event['warmer'] === true) {
            // Delay the response to ensure concurrent invocation
            // See https://github.com/brefphp/bref/pull/734
            usleep(10000); // 10ms
            return ['Lambda is warm'];
        }

        // is this a http/API Gateway event?
        if (isset($event['version'])) {
            return $this->fpmHandler->handleRequest(new HttpRequestEvent($event), $context)->toApiGatewayFormatV2();
        }

        // is this a sqs event?
        if (isset($event['Records'])) {
            // $sqsEvent = new SqsEvent($event);
            // TODO(jasonmccallister): implement SQS handler to process the queue events
            throw new RuntimeException('SQS events are not yet supported');
        }

        // is this a craft command event?
        if (isset($event['command'])) { // schedule/run
            return (new CraftCommandEvent($event, $context))->run();
        }

        throw new RuntimeException('Unknown event type');
    }
}
