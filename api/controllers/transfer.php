<?php

require_once __DIR__ . '/../helper.php';

$this->respond('POST', '/billing/create', function ($request, $response) {
    /** @var Klein\Response $response */
    /** @var Klein\Request $request */

    $xhrResponse = Roamer\Helper::xhr(
        'post',
        '/billing/create',
        [
            'billing' => $request->param('billing'),
        ],
        false,
        null
    );

    return $response->json($xhrResponse);
});

$this->respond('POST', '/billing/link', function ($request, $response) {
    /** @var Klein\Response $response */
    /** @var Klein\Request $request */

    $xhrResponse = Roamer\Helper::xhr(
        'post',
        '/billing/link/transfer',
        [
            'id'          => $request->param('billing_id'),
            'transfer_id' => $request->param('transfer_id'),
            'count'       => $request->param('count'),
        ],
        false,
        null
    );

    return $response->json($xhrResponse);
});

$this->respond('POST', '/rsim/order/create', function ($request, $response) {
    /** @var Klein\Response $response */
    /** @var Klein\Request $request */

    $bundleId = null;
    $countryCode = $request->param('bundle_country_code') === 'null' ? null :
        $request->param('bundle_country_code');
    $periodId = $request->param('bundle_period_id') === 'null' ? null :
        (int)$request->param('bundle_period_id');

    if (!empty($countryCode) && !empty($periodId)) {
        $db = Roamer\Helper::getDb();
        $bundleId = $db->queryFirstField('
                SELECT bundle.id
                FROM roamerapp.rsim_data_bundle bundle
                LEFT JOIN roamerapp.country country ON country.id = bundle.country_id
                WHERE bundle.active=1 AND bundle.period_id=%i AND country.code=%s
            ', $periodId, $countryCode);
    }

    $xhrResponse = Roamer\Helper::xhr(
        'post',
        '/rsim/order/create',
        [
            'transfer_id' => $request->param('transfer_id'),
            'sim_count'   => $request->param('sim_count'),
            'bundle_id'   => $bundleId,
        ],
        false,
        null
    );

    return $response->json($xhrResponse);
});
