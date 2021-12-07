<?php

namespace Zarcony\ReportsManager;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Zarcony\ReportsManager\Skeleton\SkeletonClass
 */
class ReportsManagerFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'reports-manager';
    }
}
