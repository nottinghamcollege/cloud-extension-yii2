<?php

if (!function_exists('craft_modify_app_config')) {
    function craft_modify_app_config(array &$config, string $appType): void
    {
        \craft\cloud\Helper::modifyConfig($config, $appType);
    }
}
