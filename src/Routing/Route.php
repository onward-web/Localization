<?php

declare(strict_types=1);

namespace Arcanedev\Localization\Routing;

use Closure;



class Route extends \Illuminate\Routing\Route
{
    public $dynamic = false;
    public $translatable = false;

    public function makeDynamic()
    {
        $this->dynamic = true;
        return $this;
    }

    public function makeTranslatable()
    {
        $this->translatable = true;
        return $this;
    }





}
