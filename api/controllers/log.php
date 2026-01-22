<?php

require_once __DIR__ . '/../helper.php';

$this->respond('POST', '/error', function ($request, $response) {
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
