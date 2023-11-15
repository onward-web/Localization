<?php

declare(strict_types=1);

namespace Arcanedev\Localization\Routing;

use Closure;
use \Arcanedev\Localization\Routing\Route;
use Illuminate\Routing\Router as LaravelRouter;

/**
 * Class     Router
 *
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 *
 * @mixin \Illuminate\Routing\Router
 */
class Router extends LaravelRouter
{


    public function newRoute($methods, $uri, $action)
    {

        return (new Route($methods, $uri, $action))
            ->setRouter($this)
            ->setContainer($this->container);
    }



}
