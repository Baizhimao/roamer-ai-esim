<?php

require_once __DIR__ . '/../helper.php';

$this->respond('POST', '/country', function ($request, $response) {
    /** @var Klein\Response $response */
    /** @var Klein\Request $request */

    $xhrResponse = Roamer\Helper::xhr(
        'get',
        '/rsim/update/country',
        $request->paramsPost()->all(),
        false,
        null
    );

    return $response->json($xhrResponse);
});
