<?php

/*
 * BearCMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) 2016 Amplilabs Ltd.
 * Free to use under the MIT license.
 */

namespace BearCMS\Internal;

use BearFramework\App;
use BearCMS\Internal\Cookies;
use BearCMS\Internal\Options;

final class Server
{

    static function call(string $name, array $arguments = [], bool $sendCookies = false, string $cacheKey = null)
    {
        $app = App::get();
        $send = function() use($name, $arguments, $sendCookies) {
            $url = Options::$serverUrl . '?name=' . $name;
            $response = self::sendRequest($url, $arguments, $sendCookies);
            if ($sendCookies && self::isRetryResponse($response)) {
                $response = self::sendRequest($url, $arguments, $sendCookies);
            }
            return $response;
        };
        if ($cacheKey !== null) {
            $cacheKey = md5($cacheKey) . md5($name) . md5(json_encode($arguments));
            $data = $app->cache->getValue($cacheKey);
            if (is_array($data)) {
                return $data['value'];
            } elseif ($data === 'invalid') {
                return [];
            } else {
                $data = $send();
                if (is_array($data) && isset($data['value'])) {
                    if (isset($data['cache']) && (int) $data['cache'] === 1) {
                        $cacheItem = $app->cache->make($cacheKey, $data);
                        $cacheItem->ttl = isset($data['cacheTTL']) ? (int) $data['cacheTTL'] : 10;
                        $app->cache->set($cacheItem);
                    }
                    return $data['value'];
                } else {
                    $cacheItem = $app->cache->make($cacheKey, 'invalid');
                    $cacheItem->ttl = 10;
                    $app->cache->set($cacheItem);
                    return [];
                }
            }
        }
        return $send()['value'];
    }

    static function proxyAjax(): string
    {
        $app = App::get();
        $formDataList = $app->request->formData->getList();
        $temp = [];
        foreach ($formDataList as $formDataItem) {
            $temp[$formDataItem->name] = $formDataItem->value;
        }
        $response = self::sendRequest(Options::$serverUrl . '-aj/', $temp, true);
        if (self::isRetryResponse($response)) {
            return json_encode(['js' => 'window.location.reload(true);'], JSON_UNESCAPED_UNICODE);
        }

        if (is_array($response['value']) && isset($response['value']['error'])) {
            return json_encode(['js' => 'alert("' . (isset($response['value']['errorMessage']) ? $response['value']['errorMessage'] : 'An error occurred! Please, try again later and contact the administrator if the problem persists!') . '");'], JSON_UNESCAPED_UNICODE);
        }

        if (isset($response['previousValues'])) {
            foreach ($response['previousValues'] as $previousValue) {
                $response['value'] = self::mergeAjaxResponses($previousValue, $response['value']);
            }
        }
        $response['value'] = self::updateAssetsUrls($response['value'], true);
        return json_encode($response['value']);
    }

    static function mergeAjaxResponses(array $response1, array $response2): array
    {
        foreach ($response2 as $key => $data) {
            if (!isset($response1[$key])) {
                $response1[$key] = is_array($data) ? [] : '';
            }
            if (is_array($data)) {
                $response1[$key] = array_merge($response1[$key], $data);
            } else {
                $response1[$key] .= $data;
            }
        }
        return $response1;
    }

    static function isRetryResponse(array $response): bool
    {
        $responseHeaders = $response['headers'];
        return strpos($responseHeaders, 'X-App-Sr: qyi') > 0 ||
                strpos($responseHeaders, 'X-App-Sr: pkr') > 0 ||
                strpos($responseHeaders, 'X-App-Sr: jke') > 0 ||
                strpos($responseHeaders, 'X-App-Sr: wpr') > 0;
    }

    static function updateAssetsUrls($content, bool $ajaxMode)
    {
        $serverUrl = \BearCMS\Internal\Options::$serverUrl;
        $app = App::get();
        $context = $app->context->get(__FILE__);
        $updateUrl = function($url) use ($app, $context, $serverUrl) {
            if (strpos($url, '?') !== false) {
                $url = explode('?', $url)[0];
            }
            return $app->assets->getUrl($context->dir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 's' . DIRECTORY_SEPARATOR . str_replace($serverUrl, '', $url), ['cacheMaxAge' => 999999999, 'version' => 1]);
        };

        if ($ajaxMode) {
            $hasChange = false;
            $contentData = $content;
            if (isset($contentData['jsFiles'])) {
                foreach ($contentData['jsFiles'] as $i => $src) {
                    if (isset($src{0}) && strpos($src, $serverUrl) === 0) {
                        $contentData['jsFiles'][$i] = $updateUrl($src);
                        $hasChange = true;
                    }
                }
            }
            if ($hasChange) {
                return $contentData;
            }
        } else {
            $hasChange = false;
            $dom = new \IvoPetkov\HTML5DOMDocument();
            $dom->loadHTML($content);
            $scripts = $dom->querySelectorAll('script');
            foreach ($scripts as $script) {
                $src = (string) $script->getAttribute('src');
                if (isset($src{0}) && strpos($src, $serverUrl) === 0) {
                    $script->setAttribute('src', $updateUrl($src));
                    $script->setAttribute('id', md5($src));
                    $hasChange = true;
                }
            }
            if ($hasChange) {
                return $dom->saveHTML();
            }
        }
        return $content;
    }

    /**
     * 
     * @param string $url
     * @param array $data
     * @param array $cookies
     * @param bool $includeLogData
     * @return array Returns an array in the following format: ['headers' => ..., 'body' => ..., 'logData' => ...]
     * @throws \Exception
     */
    static function makeRequest(string $url, array $data, array $cookies, bool $includeLogData = false): array
    {
        $app = App::get();

        $clientData = [];
        $clientData['about'] = [
            'type' => 'bearframework-addon',
            'bearFrameworkVersion' => $app::VERSION,
            'bearCMSAddonVersion' => \BearCMS::VERSION
        ];
        $clientData['siteID'] = Options::$siteID;
        if (Options::$siteSecret !== null) {
            $clientData['siteSecretHash'] = hash('sha256', Options::$siteSecret);
        }
        if (Options::$appSecretKey !== null) {
            $getHashedAppSecretKey = function() {
                $parts = explode('-', Options::$appSecretKey, 2);
                if (sizeof($parts) === 2) {
                    return strtoupper('sha256-' . $parts[0] . '-' . hash('sha256', $parts[1]));
                }
                return '';
            };
            $clientData['appSecretKey'] = $getHashedAppSecretKey();
        }
        $clientData['whitelabel'] = (int) Options::$whitelabel;
        $clientData['requestBase'] = $app->request->base;
        $clientData['cookiePrefix'] = Options::$cookiePrefix;
        if ($app->bearCMS->currentUser->exists()) {
            $currentUserData = \BearCMS\Internal\Data::getValue('bearcms/users/user/' . md5($app->bearCMS->currentUser->getID()) . '.json');
            $currentUserID = null;
            if ($currentUserData !== null) {
                $currentUserData = json_decode($currentUserData, true);
                $currentUserID = isset($currentUserData['id']) ? $currentUserData['id'] : null;
            }
            $clientData['currentUserID'] = $currentUserID;
        }

        $clientData['features'] = json_encode(Options::$features);
        $clientData['language'] = Options::$language;
        $clientData['uiColor'] = Options::$uiColor;
        $clientData['uiTextColor'] = Options::$uiTextColor;
        $clientData['adminPagesPathPrefix'] = Options::$adminPagesPathPrefix;
        $clientData['blogPagesPathPrefix'] = Options::$blogPagesPathPrefix;
        if (Options::$maxUploadsSize !== null) {
            $clientData['maxUploadsSize'] = Options::$maxUploadsSize;
            $clientData['uploadsSize'] = \BearCMS\Internal\Data\UploadsSize::getSize();
        }
        $data['clientData'] = json_encode($clientData, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'BearCMS Bear Framework Addon ' . \BearCMS::VERSION);
        if (!empty($cookies)) {
            $cookiesValues = [];
            foreach ($cookies as $key => $value) {
                $cookiesValues[] = $key . '=' . $value;
            }
            curl_setopt($ch, CURLOPT_COOKIE, implode(';', $cookiesValues));
        }
        $response = curl_exec($ch);
        $error = curl_error($ch);

        $responseHeadersSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responseHeaders = trim(substr($response, 0, $responseHeadersSize));
        $responseBody = substr($response, $responseHeadersSize);
        if (strpos($responseHeaders, 'X-App-Bg: 1') !== false) {
            try {
                $responseBody = gzuncompress($responseBody);
            } catch (\Exception $e) {
                throw new \Exception('Invalid response!');
            }
        }

        $logData = null;
        if ($includeLogData) {
            $logData = [];
            $logData['userID'] = $app->bearCMS->currentUser->getID();
            $logData['timing'] = [
                'total' => curl_getinfo($ch, CURLINFO_TOTAL_TIME),
                'dns' => curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME),
                'connect' => curl_getinfo($ch, CURLINFO_CONNECT_TIME),
                'waiting' => curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME)
            ];
            $logData['request'] = [
                'url' => $url,
                'headers' => trim(curl_getinfo($ch, CURLINFO_HEADER_OUT)),
                'data' => $data
            ];
            $logData['response'] = [
                'headers' => $responseHeaders,
                'body' => $responseBody
            ];
        }
        curl_close($ch);
        if (isset($error{0})) {
            throw new \Exception('Request curl error: ' . $error . ' (1027)');
        }
        $result = [
            'headers' => $responseHeaders,
            'body' => $responseBody
        ];
        if ($logData !== null) {
            $result['logData'] = $logData;
        }
        return $result;
    }

    /**
     * 
     * @param string $url
     * @param array $data
     * @param bool $sendCookies
     * @return array Returns an array in the following format: ['headers' => ..., 'value' => ..., 'cache' => ..., 'cacheTTL' => ...]
     */
    static function sendRequest(string $url, array $data = [], bool $sendCookies = false): array
    {
        $app = App::get();
        $context = $app->context->get(__FILE__);
        if (!is_array($data)) {
            $data = [];
        }

        $data['responseType'] = 'jsongz';
        if (isset($data['_ajaxreferer'])) {
            $data['_ajaxreferer'] = str_replace($app->request->base . '/', Options::$serverUrl, $data['_ajaxreferer']);
        }

        $cookies = $sendCookies ? Cookies::getList(Cookies::TYPE_SERVER) : [];

        $send = function($requestData = [], $counter = 1) use(&$send, $app, $url, $data, $cookies, $context) {
            if ($counter > 10) {
                throw new \Exception('Too much requests');
            }
            $requestResponse = self::makeRequest($url, array_merge($data, $requestData, ['requestNumber' => $counter]), $cookies, Options::$logServerRequests);
            if (self::isRetryResponse($requestResponse)) {
                return $requestResponse;
            }
            $requestResponseBody = json_decode($requestResponse['body'], true);
            if (!is_array($requestResponseBody) || !array_key_exists('response', $requestResponseBody)) {
                throw new \Exception('Invalid response. Body: ' . $requestResponse['body']);
            }
            $requestResponseData = $requestResponseBody['response'];

            $response = new \ArrayObject([// Must be ArrayObject so it can be passed by reference to the internal commands
                'headers' => $requestResponse['headers'],
                'value' => isset($requestResponseData['value']) ? $requestResponseData['value'] : '',
                'cache' => isset($requestResponseData['cache']) ? (int) $requestResponseData['cache'] > 0 : false,
                'cacheTTL' => isset($requestResponseData['cacheTTL']) ? (int) $requestResponseData['cacheTTL'] : 0,
            ]);

            $requestResponseMeta = isset($requestResponseData['meta']) ? $requestResponseData['meta'] : [];

            if (Options::$logServerRequests) {
                $logData = $requestResponse['logData'];
                $logData['response']['data'] = [
                    'value' => $response['value'],
                    'meta' => $requestResponseMeta,
                    'cache' => $response['cache'],
                    'cacheTTL' => $response['cacheTTL']
                ];
                $app->logger->log('bearcms-server-requests', print_r($logData, true));
            }

            $resend = isset($requestResponseMeta['resend']) && (int) $requestResponseMeta['resend'] > 0;
            $resendData = [];

            if (isset($requestResponseMeta['commands']) && is_array($requestResponseMeta['commands'])) {
                $commandsResults = [];
                foreach ($requestResponseMeta['commands'] as $commandData) {
                    if (isset($commandData['name']) && isset($commandData['data'])) {
                        $commandResult = '';
                        $commandFilename = $context->dir . '/classes/BearCMS/Internal/ServerCommands/' . str_replace(['.', '/', '\\'], '', $commandData['name']) . '.php';
                        $callback = null;
                        if (is_file($commandFilename)) {
                            $callback = include $commandFilename;
                        }
                        if (is_callable($callback)) {
                            $commandResult = call_user_func($callback, $commandData['data'], $response);
                        }
                        if (isset($commandData['key'])) {
                            $commandsResults[$commandData['key']] = $commandResult;
                        }
                    }
                }
                if ($resend) {
                    $resendData['commandsResults'] = json_encode($commandsResults, JSON_UNESCAPED_UNICODE);
                }
            }

            if (isset($requestResponseMeta['clientEvents'])) {
                $resendData['clientEvents'] = $requestResponseMeta['clientEvents'];
                $resend = true;
            }

            if (isset($requestResponseMeta['currentUser'])) {
                for ($i = 1; $i <= 3; $i++) {
                    try {
                        $currentUserData = $requestResponseMeta['currentUser'];
                        $dataKey = '.temp/bearcms/userkeys/' . md5($currentUserData['key']);
                        $app->data->set($app->data->make($dataKey, $currentUserData['id']));
                        \BearCMS\Internal\Data::setChanged($dataKey);
                        break;
                    } catch (\BearFramework\App\Data\DataLockedException $e) {
                        
                    }
                    if ($i === 3) {
                        throw $e;
                    } else {
                        sleep(1);
                    }
                }
            }

            $previousValues = [];
            if (isset($requestResponseMeta['clientEvents'])) {
                $previousValues[] = $response['value']; // Can be changed in a command so use from the response object
            }
            if ($resend) {
                $response = $send($resendData, $counter + 1);
            }
            if (!empty($previousValues)) {
                $response['previousValues'] = $previousValues;
            }
            return $response;
        };
        $response = $send();
        if ($sendCookies) {
            Cookies::setList(Cookies::TYPE_SERVER, Cookies::parseServerCookies($response['headers']));
        }
        return (array) $response;
    }

}
