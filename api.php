<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';

/** @var \Klein\Klein $klein */
$klein = new \Klein\Klein();

$klein->with('/api', function () use ($klein) {
    foreach (['beta', 'global', 'rates', 'sim', 'transfer', 'geoip', 'log'] as $controller) {
        $klein->with("/$controller", "api/controllers/$controller.php");
    }

    $klein->respond(function ($request, $response) {
        /** @var \Klein\Response $response */
        return $response->json([
            'error'   => 404,
            'message' => 'The requested page could not be found but may be available again in the future',
        ]);
    });
});

$klein->dispatch();
