<?php

namespace craft\cloud;

enum PurgeModeEnum: string
{
    case TAG = 'tag';
    case ALL = 'all';
}
