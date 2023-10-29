<?php

namespace craft\cloud;

enum PurgeModeEnum: string
{
    case TAGS = 'tags';
    case ALL = 'all';
}
