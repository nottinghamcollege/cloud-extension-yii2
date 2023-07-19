<?php

namespace craft\cloud\runtime\event;

use Bref\Context\Context;
use Bref\Event\Handler;
use Bref\Event\Http\HttpRequestEvent;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\Sqs\SqsEvent;
use Bref\FpmRuntime\FastCgi\FastCgiCommunicationFailed;
use Bref\FpmRuntime\FastCgi\Timeout;
use Bref\FpmRuntime\FpmHandler;
use JsonException;
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
     * @throws JsonException
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

        // is this a sqs event?
        if (isset($event['Records'])) {
            foreach ((new SqsEvent($event))->getRecords() as $record) {
                try {
                    (new QueueExecCommand($record, $context))->handle();
                } catch (RuntimeException $e) {
                    // echo the exception to the output but continue processing the other records
                    echo $e->getMessage();
                }
            }
        }

        // is this a craft command event?
        if (isset($event['command'])) {
            return (new CraftCommandEvent($event, $context))->run();
        }

        // default to API Gateway v2 events
        return $this->fpmHandler->handleRequest(new HttpRequestEvent($event), $context)->toApiGatewayFormatV2();
    }
}
