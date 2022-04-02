<?php

/*
 *
 *
 * (c) Allen, Li <morningbuses@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Goodcatch\FXK\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class FXK
 * @package Goodcatch\FXK\Facades
 */
class FXK extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'fxiaoke';
    }
}