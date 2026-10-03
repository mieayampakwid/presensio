<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Route;

readonly class Message
{
    public string $url;

    /**
     * @param  array<string, mixed>  $routeParameters
     */
    public function __construct(
        public string $title,
        public string $body,
        public string $route,
        public array $routeParameters = [],
    ) {
        $this->url = Route::has($this->route)
            ? route($this->route, $this->routeParameters, false)
            : $this->route;
    }
}
