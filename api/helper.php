<?php

namespace Roamer;

class Helper
{

    /**
     * @return string
     */
    public static function makeWsse()
    {
        $date = new \DateTime();
        //$date->modify('-10 second');

        $appToken = self::getApp();
        $token = [
            'username' => $appToken['token'],
            'secret'   => $appToken['secret'],
            'created'  => $date->format('r'),
            'nonce'    => uniqid('web') . rand(100000, 999999),
        ];

        $token['digest'] = base64_encode(hash('sha512', $token['nonce'] . $token['created'] . $token['secret']));
        $wsse = 'UsernameToken Username="' . $token['username'] . '", PasswordDigest="' . $token['digest'] .
            '", Nonce="' . base64_encode($token['nonce']) . '", Created="' . $token['created'] . '"';

        return $wsse;
    }

    /**
     * @return string
     */
    public static function getEntryPoint()
    {
        $api3 = 'https://api3.roamerapp.com';
        if (strpos($_SERVER['HTTP_HOST'], 'stage') !== false) {
            $api3 = 'https://stage-api3.roamerapp.com';
        } else {
            switch (self::getEnv()) {
                case 'local':
                case 'andrewi':
                    $api3 = 'https://lan-api3.roamerapp.com';
                    break;
                case 'development':
                    $api3 = 'https://dev-api3.roamerapp.com';
                    break;
            }
        }

        return $api3;
    }

    /**
     * @return string
     */
    public static function getIp()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        if (substr($ip, 0, strlen('192.168.1.')) === '192.168.1.'
            && in_array(self::getEnv(), ['local', 'development', 'local-development'])
        ) {
            $ip = '127.0.0.1';
        }

        return $ip;
    }

    /**
     * @return string
     */
    public static function getAppId()
    {
        if (!empty($_SESSION['app_id'])) {
            return $_SESSION['app_id'];
        }
        $headers = [
            'X-App-Build' => '3',
            'X-Platform'  => 'web',
            'X-Real-IP'   => self::getIp(),
            'X-Lang'      => $_COOKIE['locale'] ? $_COOKIE['locale'] : 'en',
        ];
        $client = self::getClient();
        $entryPoint = self::getEntryPoint();
        try {
            $clientResponse = $client->get($entryPoint . '/track/app-id', [
                'headers' => $headers,
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($e->hasResponse()) {
                $response = json_decode($e->getResponse()->getBody(), true);
                if (!empty($response['app-id'])) {
                    return $response['app-id'];
                }
            }
            return '';
        }

        $response = json_decode($clientResponse->getBody(), true);
        $_SESSION['app_id'] = $response['app-id'];
        return $response['app-id'];
    }

    /**
     * @return array
     */
    public static function getApp()
    {
        $appId = self::getAppId();
        $db = self::getDb();
        $appToken = $db->queryFirstRow('
            SELECT app_token.*
            FROM roamerapp.app app
            LEFT JOIN roamerapp.app_token app_token ON app_token.id = app.id
            WHERE app.unique_id="' . $appId . '"
        ');
        return $appToken;
    }

    /**
     * @param bool|false $setWsse
     * @param array|null $location
     * @return array
     */
    public static function createHeaders($setWsse = false, $location = null)
    {
        $headers = [
            'X-App-Build' => '3',
            'X-Real-IP'   => self::getIp(),
            'X-Lang'      => $_COOKIE['locale'] ? $_COOKIE['locale'] : 'en',
            'X-Platform'  => 'web',
        ];

        if ($setWsse) {
            $headers['X-Wsse'] = self::makeWsse();
        }

        if (!empty($location)) {
            $headers['X-Location'] = $location;
        }

        return $headers;
    }

    /**
     * @param string          $method
     * @param string          $entryPoint
     * @param array           $data
     * @param bool|false      $setWsse
     * @param array|null      $location
     * @return array
     */
    public static function xhr($method, $entryPoint, $data = [], $setWsse = false, $location = null)
    {
        $client = self::getClient();
        try {
            if ($method == 'get') {
                $clientResponse = $client->get(self::getEntryPoint() . $entryPoint . '?' . http_build_query($data), [
                    'headers' => Helper::createHeaders($setWsse, $location),
                ]);
            } else {
                $clientResponse = $client->post(self::getEntryPoint() . $entryPoint, [
                    'headers' => Helper::createHeaders($setWsse, $location),
                    'json'    => $data,
                ]);
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($e->hasResponse()) {
                $response = json_decode($e->getResponse()->getBody(), true);
                $response['message'] = 'The request was a legal request, but the server is refusing to respond to it.' .
                    ' For use when authentication is possible but has failed or not yet been provided';
                return $response;
            }

            return [
                'error'   => 406,
                'message' => 'The server can only generate a response that is not accepted by the client',
            ];
        }

        return json_decode($clientResponse->getBody(), true);
    }

    /**
     * @return \GuzzleHttp\Client
     */
    public static function getClient()
    {
        return new \GuzzleHttp\Client();
    }

    /**
     * @return \MeekroDB
     */
    public static function getDb()
    {
        $config = self::getConfig();

        return new \MeekroDB(
            $config['database']['host'],
            $config['database']['user'],
            $config['database']['password'],
            $config['database']['dbname'],
            $config['database']['port']
        );
    }


    /**
     * @return string
     */
    private static function getEnv()
    {
        return getenv('APPLICATION_ENV');
    }

    /**
     * @return array
     */
    private static function getConfig()
    {
        return require_once __DIR__ . '/../../config/' . self::getEnv() . '.php';
    }
}
