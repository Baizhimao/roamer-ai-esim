<?php

require_once __DIR__ . '/../helper.php';

$this->respond('GET', '/prices', function ($request, $response) {
    /** @var Klein\Response $response */
    /** @var Klein\Request $request */

    $db = Roamer\Helper::getDb();

    $countryCode = $request->param('code');
    $prices = $db->query('
            SELECT period.id as id, period.duration, period.price, bundle.amount as size,
            rent_type.price as local, park_type.price as home
            FROM roamerapp.rsim_data_bundle_period period
            LEFT JOIN roamerapp.rsim_data_bundle bundle ON bundle.period_id = period.id
            LEFT JOIN roamerapp.country country ON country.id = bundle.country_id
            LEFT JOIN roamerapp.rsim_did_rent_type rent_type ON period.duration = rent_type.duration
            AND country.supported = 1 AND rent_type.active=1
            LEFT JOIN roamerapp.rsim_park_type park_type ON period.duration = park_type.duration
            AND country.supported = 1 AND park_type.active=1
            WHERE country.code="' . $countryCode . '" AND period.price > 0 AND bundle.active=1 AND bundle.public=1
            ORDER BY period.price ASC
        ');
    foreach ($prices as $key => $price) {
        $prices[$key]['size'] = ceil($price['size'] / 1024);
        $prices[$key]['label'] = '<b>' . $prices[$key]['size'] . '</b> MB';
    }

    return $response->json($prices);
});

$this->respond('GET', '/forward', function ($request, $response) {
    /** @var Klein\Response $response */
    /** @var Klein\Request $request */

    $db = Roamer\Helper::getDb();

    $countries = $db->query('
            SELECT code
            FROM roamerapp.country
            WHERE free_forward=1
        ');
    
    foreach ($countries as $key => $country) {
        $countries[$key] = $country['code'];
    }

    return $response->json($countries);
});

$this->respond('GET', '/dids', function ($request, $response) {
    /** @var Klein\Response $response */
    /** @var Klein\Request $request */

    $db = Roamer\Helper::getDb();

    $dids = $db->query('
        SELECT did.duration as duration, did.price as local
        FROM roamerapp.rsim_did_rent_type did
        WHERE did.active=1
    ');

    return $response->json($dids);
});

$this->respond('POST', '/beta/request/validate', function ($request, $response) {
    /** @var Klein\Response $response */
    /** @var Klein\Request $request */

    $db = Roamer\Helper::getDb();

    $valid = true;
    $data = $request->param('data');

    if (empty($data['hash']) || empty($data['email'])) {
        return $response->json(['valid' => false]);
    }

    $foundBetaRequest = $db->queryFirstField('
        SELECT COUNT(s.id)
        FROM roamerapp.beta_test_subscribe s
        WHERE s.email=%s AND
        s.hash=%s AND
        s.accepted=1
        ', $data['email'], $data['hash']);

    if ($foundBetaRequest < 1) {
        $valid = false;
    }

    if ($valid) {
        $db->update('roamerapp.beta_test_subscribe', [
            'accepted' => 0,
        ], 'email=%s AND hash=%s', $data['email'], $data['hash']);
    }

    return $response->json(['valid' => $valid]);
});

$this->respond('POST', '/beta/get', function ($request, $response) {
    /** @var Klein\Response $response */
    /** @var Klein\Request $request */

    $db = Roamer\Helper::getDb();

    $email = $request->param('email');
    $hash = $request->param('hash');

    if (empty($email) || empty($hash)) {
        return $response->json(['error' => 300]);
    }

    $foundBetaRequest = $db->queryFirstRow('
        SELECT s.email as email, s.hash as hash, c.code as country
        FROM roamerapp.beta_test_subscribe s
        LEFT JOIN roamerapp.country c ON c.id = s.destination_country_id
        WHERE s.email=%s AND s.hash=%s AND s.accepted=1
        ', $email, $hash);

    if (empty($foundBetaRequest)) {
        return $response->json(['error' => 400]);
    }

    return $response->json(['error' => 0, 'subscription' => $foundBetaRequest]);
});

$this->respond('POST', '/request/validate', function ($request, $response) {
    /** @var Klein\Response $response */
    /** @var Klein\Request $request */

    $db = Roamer\Helper::getDb();

    $valid = true;
    $count = (int)$request->param('count');
    $price = $request->param('price');
    $destinationCountryCode = $request->param('destination');
    $duration = $request->param('duration') === 'null' ? null : (int)$request->param('duration');
    $vat = $db->queryFirstField('SELECT vat FROM roamerapp.country WHERE code=%s', $destinationCountryCode);

    $deliveryFeeOrBundlePrice = empty($duration) ? 5 : $db->queryFirstField(
        'SELECT price FROM roamerapp.rsim_data_bundle_period WHERE id=%i',
        $duration
    );
    $calculatedPrice = $deliveryFeeOrBundlePrice * $count * (1 + ($vat / 100));
    if (round($calculatedPrice, 2) != round($price, 2)) {
        $valid = false;
    }

    return $response->json(['valid' => $valid]);
});

$this->respond('POST', '/request/promo/validate', function ($request, $response) {
    /** @var Klein\Response $response */
    /** @var Klein\Request $request */

    $db = Roamer\Helper::getDb();

    /**
     * validate promo || or on request
     * $request->param('promo');
     * Insert API3 check for promo from ctrl+4
     */

    $xhrResponse = Roamer\Helper::xhr(
        'get',
        '/v2/promo',
        [
            'code' => $request->param('promo'),
        ],
        false,
        null
    );
    if (!$xhrResponse['valid']) {
        return $response->json(['valid' => false]);
    }
    if ($xhrResponse['type'] != 33) {
        return $response->json(['valid' => false]);
    }

    $valid = true;
    $count = (int)$request->param('count');
    if ($count != 1) {
        $valid = false;
    }
    $price = $request->param('price');
    $destinationCountryCode = $request->param('destination');
    $duration = $request->param('duration') === 'null' ? null : (int)$request->param('duration');
    $vat = $db->queryFirstField('SELECT vat FROM roamerapp.country WHERE code=%s', $destinationCountryCode);

    $deliveryFeeOrBundlePrice = empty($duration) ? 0 : $db->queryFirstField(
        'SELECT price FROM roamerapp.rsim_data_bundle_period WHERE id=%i',
        $duration
    );
    $calculatedPrice = $deliveryFeeOrBundlePrice * $count * (1 + ($vat / 100));
    if (round($calculatedPrice, 2) != round($price, 2)) {
        $valid = false;
    }

    return $response->json(['valid' => $valid]);
});

$this->respond('POST', '/request/stripe', function ($request, $response) {
    /** @var Klein\Response $response */
    /** @var Klein\Request $request */

    $token = $request->param('token');
    $userData = $request->param('user');
    $fromApp = (int)$request->param('app');

    $xhrResponse = Roamer\Helper::xhr(
        'post',
        '/payment/stripe/charge',
        [
            'token'      => $token['id'],
            'user_id'    => !empty($userData['id']) ? $userData['id'] : 0,
            'amount'     => $request->param('amount'),
            'promo_code' => $request->param('promo') == '' ? null : $request->param('promo'),
            'purpose'    => 10, // PURPOSE_RSIM
            'source'     => $fromApp === 1 ? 1 : 2, // 1-app, 2-web
            'vat'        => 0,
            'email'      => $token['email'],
        ],
        true,
        $userData['location']
    );

    return $response->json($xhrResponse);
});

$this->respond('POST', '/request/charge', function ($request, $response) {
    /** @var Klein\Response $response */
    /** @var Klein\Request $request */

    $userData = $request->param('user');
    $email = $request->param('email');
    $fromApp = (int)$request->param('app');

    $xhrResponse = Roamer\Helper::xhr(
        'post',
        '/v2/payment/dummy/charge',
        [
            'user_id'    => !empty($userData['id']) ? $userData['id'] : 0,
            'email'      => $email,
            'purpose'    => 10, // PURPOSE_RSIM
            'source'     => $fromApp === 1 ? 1 : 2, // 1-app, 2-web
            'promo_code' => $request->param('promo'),
        ],
        true,
        $userData['location']
    );

    return $response->json($xhrResponse);
});

$this->respond('POST', '/request/paypal', function ($request, $response) {
    /** @var Klein\Response $response */
    /** @var Klein\Request $request */

    $userData = $request->param('user');
    $fromApp = (int)$request->param('app');

    $xhrResponse = Roamer\Helper::xhr(
        'post',
        '/payment/paypal/start-payment',
        [
            'user_id'     => !empty($userData['id']) ? $userData['id'] : 0,
            'amount'      => $request->param('amount'),
            'promo_code'  => $request->param('promo') == '' ? null : $request->param('promo'),
            'description' => $request->param('description'),
            'purpose'     => 10, // PURPOSE_RSIM
            'source'      => $fromApp === 1 ? 1 : 2, // 1-app, 2-web
            'vat'         => 0,
            'success_url' => $request->param('success_url'),
            'fail_url'    => $request->param('fail_url'),
        ],
        true,
        $userData['location']
    );

    return $response->json($xhrResponse);
});

$this->respond('POST', '/promo', function ($request, $response) {
    /** @var Klein\Response $response */
    /** @var Klein\Request $request */

    $xhrResponse = Roamer\Helper::xhr(
        'get',
        '/v2/promo',
        [
            'code' => $request->param('promo'),
        ],
        false,
        null
    );

    return $response->json($xhrResponse);
});
