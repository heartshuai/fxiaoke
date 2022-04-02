<?php

/*
 *
 *
 * (c) Allen, Li <morningbuses@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | fxiaoke open api settings
    |--------------------------------------------------------------------------
    |
    */

    'url' => env('FXK_API', 'https://open.fxiaoke.com/cgi/'),
    'appId' => env('FXK_APPID', ''),
    'appSecret' => env('FXK_SECRET', ''),
    'permanentCode' => env('FXK_PERMANENT_CODE', ''),
    'adminUser' => env('FXK_ADMIN_USER', ''),

    /*
    |--------------------------------------------------------------------------
    | Request time out
    |--------------------------------------------------------------------------
    |
    | seconds of timeout
    |
    */
    'timeout' => env('FXK_TIMEOUT', 2),

];
