<?php

namespace craft\cloud\queue;

class Queue extends \yii\queue\sqs\Queue
{
    public $serializer = Serializer::class;
}
