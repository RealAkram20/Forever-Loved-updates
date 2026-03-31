<?php

namespace App\SiteBlocks;

use App\Contracts\SiteBlockContract;

abstract class AbstractSiteBlock implements SiteBlockContract
{
    public static function category(): string
    {
        return 'General';
    }
}
