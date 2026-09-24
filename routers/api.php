<?php

/** @var Core\Router $router */

use Controllers\ApiController;

// Reached as /api/<endpoint>; the "/api" prefix is stripped before matching.
// Responses are JSON. Unauthenticated calls get 401, role-restricted ones 403,
// and a POST without a valid CSRF token gets 419.
$router->get('/health', [ApiController::class, 'health'], ['public' => true]);
$router->get('/me', [ApiController::class, 'me']);

$router->get('/my/trips', [ApiController::class, 'myTrips'], ['permission' => 'trips']);
$router->get('/my/deliveries', [ApiController::class, 'myDeliveries'], ['permission' => 'deliveries']);
$router->post('/deliveries/{id}/{action}', [ApiController::class, 'deliveryAction'], ['permission' => 'deliveries', 'ability' => 'edit']);

$router->get('/modules/{module}', [ApiController::class, 'listing']);
