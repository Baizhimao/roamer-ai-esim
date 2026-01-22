<?php

require_once __DIR__ . '/../helper.php';

$this->respond('POST', '/log/error', function ($request, $response) {
    /** @var Klein\Response $response */
    /** @var Klein\Request $request */

    $xhrResponse = Roamer\Helper::xhr(
        'post',
        '/track/log',
        [
            'secret' => 'fraud2romin',
            'level'  => $request->param('level', 2),
            'title'  => 'Error in ' . $request->param('area', 'Undefined'),
            'data'   => [
                'message'   => $request->param('message'),
                'area'      => $request->param('area', 'Undefined'),
                'userAgent' => $request->param('userAgent'),
            ],
        ],
        false,
        $request->param('location')
    );

    return $response->json($xhrResponse);
});

$this->respond('GET', '/geoip', function ($request, $response) {
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
