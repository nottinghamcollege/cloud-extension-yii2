<?php

namespace craft\cloud;

enum EventEnum: string
{
    case BEFORE_UP = 'beforeUp';
    case AFTER_UP = 'afterUp';
}
