<?php

namespace craft\cloud;

enum HeaderEnum: string
{
    case CACHE_TAG = 'Cache-Tag';
    case CACHE_PURGE = 'Cache-Purge';
    case CACHE_CONTROL = 'Cache-Control';
    case AUTHORIZATION = 'Authorization';
    case MUTEX_ACQUIRE_LOCK = 'Mutex-Acquire-Lock';
    case MUTEX_RELEASE_LOCK = 'Mutex-Release-Lock';
}
