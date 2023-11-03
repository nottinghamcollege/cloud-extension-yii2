<?php

namespace craft\cloud;

enum HeaderEnum: string
{
    case CACHE_TAG = 'Cache-Tag';
    case CACHE_TAG_PURGE = 'Cache-Tag-Purge';
    case CACHE_CONTROL = 'Cache-Control';
}
