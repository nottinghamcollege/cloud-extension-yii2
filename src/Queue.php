<?php

namespace craft\cloud;

use yii\base\NotSupportedException;

class Queue extends \yii\queue\sqs\Queue
{
    /**
     * @inheritdoc
     */
    protected function pushMessage($message, $ttr, $delay, $priority): string
    {
        if ($priority) {
            throw new NotSupportedException('Priority is not supported in this driver');
        }

        $request = [
            'QueueUrl' => $this->url,
            'MessageBody' => $message,
            'DelaySeconds' => $delay,
            'MessageAttributes' => [
                'TTR' => [
                    'DataType' => 'Number',
                    'StringValue' => $ttr,
                ],
                'EnvironmentId' => [
                    'DataType' => 'String',
                    'StringValue' => Module::getInstance()->getConfig()->environmentId,
                ]
            ],
        ];

        if (str_ends_with($this->url, '.fifo')) {
            $request['MessageGroupId'] = $this->messageGroupId;
            $request['MessageDeduplicationId'] = hash('sha256', $message);
        }

        $response = $this->getClient()->sendMessage($request);
        return $response['MessageId'];
    }
}
