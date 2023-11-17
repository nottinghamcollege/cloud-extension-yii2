<?php

namespace craft\cloud;

enum HeaderEnum: string
{
    case CACHE_TAG = 'Cache-Tag';
    case CACHE_PURGE = 'Cache-Purge';
    case CACHE_CONTROL = 'Cache-Control';
    case AUTHORIZATION = 'Authorization';
}
