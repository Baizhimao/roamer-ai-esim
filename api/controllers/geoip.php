<?php

require_once __DIR__ . '/../helper.php';

$this->respond('GET', '', function ($request, $response) {
    /** @var Klein\Response $response */
    /** @var Klein\Request $request */

    $xhrResponse = Roamer\Helper::xhr(
        'get',
        '/update/location',
        [],
        false,
        null
    );

    return $response->json($xhrResponse);
});
