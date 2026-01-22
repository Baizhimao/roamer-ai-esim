<?php

require_once __DIR__ . '/../helper.php';

$this->respond('POST', '/store', function ($request, $response) {
    /** @var Klein\Response $response */
    /** @var Klein\Request $request */

    $db = Roamer\Helper::getDb();

    $travel_period = $request->param('travel_period');
    $destination_country = $request->param('destination_country');
    $email = $request->param('email');
    $phone = $request->param('phone');

    if (empty($travel_period) || empty($destination_country) || empty($email)) {
        return $response->json(['success' => false]);
    }

    $countryId = $db->queryFirstField('SELECT id FROM roamerapp.country WHERE code=%s', $destination_country);
    if (empty($countryId)) {
        return $response->json(['success' => false]);
    }

    $exist = $db->queryFirstField('SELECT COUNT(id) FROM roamerapp.beta_test_subscribe WHERE email=%s', $email);
    if ($exist > 0) {
        return $response->json(['success' => false]);
    }

    $db->insert('roamerapp.beta_test_subscribe', [
        'email'                  => $email,
        'phone'                  => $phone,
        'travel_period'          => $travel_period,
        'destination_country_id' => $countryId,
        'hash'                   => sha1('betaTest' . $email . $countryId . $travel_period),
    ]);
    $success = $db->insertId() > 0;

    return $response->json(['success' => $success]);
});
