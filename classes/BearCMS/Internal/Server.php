<?php

/*
 * Bear CMS addon for Bear Framework
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

    static function call(string $name, array $arguments = [], bool $sendCookies = false)
    {
        $url = Options::$serverUrl . '?name=' . $name;
        $response = self::sendRequest($url, $arguments, $sendCookies);
        if ($sendCookies && self::isRetryResponse($response)) {
            $response = self::sendRequest($url, $arguments, $sendCookies);
        }
        return $response['body'];
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
            return json_encode(array('js' => 'window.location.reload(true);'), JSON_UNESCAPED_UNICODE);
        }

        if (isset($response['bodyPrefix'])) {
            $response['body'] = self::mergeAjaxResponses($response['bodyPrefix'], $response['body']);
        }
        $response['body'] = self::updateAssetsUrls($response['body'], true);
        return json_encode($response['body']);
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

    static function isRetryResponse(\ArrayObject $response): bool
    {
        $responseHeader = $response['header'];
        return strpos($responseHeader, 'X-App-Sr: qyi') > 0 ||
                strpos($responseHeader, 'X-App-Sr: pkr') > 0 ||
                strpos($responseHeader, 'X-App-Sr: jke') > 0 ||
                strpos($responseHeader, 'X-App-Sr: wpr') > 0;
    }

    static function getAssetsUrl(array $urls): string
    {
        $app = App::get();
        sort($urls);
        $resultKey = '.temp/bearcms/assets/' . md5(serialize($urls)) . '.js';
        $result = $app->data->getValue($resultKey);
        if ($result === null) {
            $filesToDownload = [];
            foreach ($urls as $url) {
                $key = '.temp/bearcms/assets/' . md5(serialize([$url])) . '.js';
                $result = $app->data->getValue($key);
                if ($result === null) {
                    $filesToDownload[$key] = $url;
                }
            }
            if (!empty($filesToDownload)) {

                $downloadFiles = function($urls) use ($app) {
                    $urls = array_values($urls);
                    $mh = curl_multi_init();

                    $serverUrlData = parse_url(\BearCMS\Internal\Options::$serverUrl);
                    $serverUrlScheme = isset($serverUrlData['scheme']) ? $serverUrlData['scheme'] : 'http';

                    foreach ($urls as $i => $url) {
                        $calls[$i] = curl_init();
                        curl_setopt($calls[$i], CURLOPT_URL, strpos($url, '//') === 0 ? $serverUrlScheme . ':' . $url : $url);
                        curl_setopt($calls[$i], CURLOPT_RETURNTRANSFER, 1);
                        curl_multi_add_handle($mh, $calls[$i]);
                    }

                    $active = null;
                    do {
                        $mrc = curl_multi_exec($mh, $active);
                    } while ($mrc == CURLM_CALL_MULTI_PERFORM);

                    while ($active && $mrc == CURLM_OK) {
                        $selectResult = curl_multi_select($mh);
                        if ($selectResult === -1) {
                            usleep(50);
                        }
                        do {
                            $mrc = curl_multi_exec($mh, $active);
                        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
                    }

                    $result = [];
                    foreach ($calls as $i => $resource) {
                        $result[$urls[$i]] = curl_multi_getcontent($resource);
                        if (strlen($app->config->logsDir) > 0) {
                            $log = 'Bear CMS asset download:' . "\n";
                            $log .= 'Url: ' . $urls[$i] . "\n";
                            $log .= 'Time: ' . curl_getinfo($resource, CURLINFO_TOTAL_TIME) . ' / dns: ' . curl_getinfo($resource, CURLINFO_NAMELOOKUP_TIME) . ', connect: ' . curl_getinfo($resource, CURLINFO_CONNECT_TIME) . ', download: ' . curl_getinfo($resource, CURLINFO_STARTTRANSFER_TIME) . "\n\n";
                            $app->logger->log('info', $log);
                        }
                        curl_multi_remove_handle($mh, $resource);
                    }
                    curl_multi_close($mh);
                    return $result;
                };

                $filesDownloadResult = $downloadFiles($filesToDownload);
                $downloadErrorUrls = [];
                foreach ($filesToDownload as $key => $url) {
                    if (strlen($filesDownloadResult[$url]) === 0) {
                        $downloadErrorUrls[] = $url;
                    } else {
                        $app->data->set($app->data->make($key, $filesDownloadResult[$url]));
                        $app->data->makePublic($key);
                    }
                }
                if (!empty($downloadErrorUrls)) {
                    throw new \Exception('Cannot download ' . implode(',', $downloadErrorUrls));
                }
            }

            if (sizeof($urls) > 1) {
                $bundleContent = '';
                foreach ($urls as $url) {
                    $key = '.temp/bearcms/assets/' . md5(serialize([$url])) . '.js';
                    $result = $app->data->getValue($key);
                    if ($result === null) {
                        throw new \Exception('Cannot read the temp file for ' . $url);
                    }
                    $bundleContent .= $result['body'];
                }
                $app->data->set($app->data->make($resultKey, $bundleContent));
                $app->data->makePublic($resultKey);
            }
        }
        return $app->assets->getUrl($app->data->getFilename($resultKey));
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
            return $app->assets->getUrl($context->dir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 's' . DIRECTORY_SEPARATOR . str_replace($serverUrl, '', $url));
        };

        if ($ajaxMode) {
            $hasChange = false;
            $contentData = $content; //json_decode($content, true);
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
                    $hasChange = true;
                }
            }
            if ($hasChange) {
                return $dom->saveHTML();
            }
        }
        return $content;
    }

    static function makeRequest(string $url, array $data, array $cookies): \ArrayObject
    {
        $app = App::get();

        $clientData = [];
        $clientData['info'] = [
            'type' => 'bearframework-addon',
            'bearframeworkVersion' => $app::VERSION,
            'addonVersion' => \BearCMS::VERSION
        ];
        $clientData['siteID'] = Options::$siteID;
        $clientData['siteSecretHash'] = hash('sha256', Options::$siteSecret);
        $clientData['requestBase'] = $app->request->base;
        $clientData['cookiePrefix'] = Options::$cookiePrefix;
        if ($app->bearCMS->currentUser->exists()) {
            $currentUserData = $app->data->getValue('bearcms/users/user/' . md5($app->bearCMS->currentUser->getID()) . '.json');
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

        $responseHeaderSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responseHeader = trim(substr($response, 0, $responseHeaderSize));
        $responseBody = substr($response, $responseHeaderSize);
        if (strpos($responseHeader, 'X-App-Bg: 1') !== false) {
            try {
                $responseBody = gzuncompress($responseBody);
            } catch (\Exception $e) {
                throw new \Exception('Invalid response');
            }
        }
        $log = "Bear CMS server request:\n";
        $log .= 'User: ' . $app->bearCMS->currentUser->getID() . "\n";
        $log .= 'Time: ' . curl_getinfo($ch, CURLINFO_TOTAL_TIME) . ' / dns: ' . curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME) . ', connect: ' . curl_getinfo($ch, CURLINFO_CONNECT_TIME) . ', download: ' . curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME) . "\n";
        $log .= 'Request: ' . trim(curl_getinfo($ch, CURLINFO_HEADER_OUT)) . "\n";
        if (Options::$logServerRequestsData) {
            $log .= 'Data: ' . trim(print_r($data, true)) . "\n";
        }
        curl_close($ch);
        foreach ($cookies as $key => $value) {
            $log = str_replace($value, '*' . strlen($value) . 'chars*', $log);
        }
        $log .= 'Response: ' . $responseHeader . "\n";
        $newCookies = Cookies::parseServerCookies($responseHeader);
        foreach ($newCookies as $newCookie) {
            $log = str_replace($newCookie['value'], '*' . strlen($newCookie['value']) . 'chars*', $log);
        }
        //$log .= 'Response body: ' . $responseBody;
        $log .= 'Body: ' . '*' . strlen($responseBody) . 'chars*';
        if (strlen($app->config->logsDir) > 0) {
            $app->logger->log('info', $log);
        }
        if (isset($error{0})) {
            throw new \Exception('Request curl error: ' . $error . ' (1027)');
        }
        return new \ArrayObject(['header' => $responseHeader, 'body' => $responseBody]);
    }

    static function sendRequest(string $url, array $data = [], bool $sendCookies = false): \ArrayObject
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
            $response = self::makeRequest($url, array_merge($data, $requestData, ['requestNumber' => $counter]), $cookies);
            if (self::isRetryResponse($response)) {
                return $response;
            }
            $responseData = json_decode($response['body'], true);
            if (!is_array($responseData) || !array_key_exists('response', $responseData)) {
                throw new \Exception('Invalid response. Body: ' . $response['body']);
            }
            $responseData = $responseData['response'];
            $response['body'] = $responseData['body'];
            $responseMeta = $responseData['meta'];

            if (Options::$logServerRequestsData) {
                if (strlen($app->config->logsDir) > 0) {
                    $log = "Bear CMS response data:\n";
                    $log .= 'Data: ' . trim(print_r($responseData, true));
                    $app->logger->log('info', $log);
                }
            }

            $resend = isset($responseMeta['resend']) && (int) $responseMeta['resend'] > 0;
            $resendRequestData = [];

            if (isset($responseMeta['commands']) && is_array($responseMeta['commands'])) {
                $commandsResults = [];
                foreach ($responseMeta['commands'] as $commandData) {
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
                    $resendRequestData['commandsResults'] = json_encode($commandsResults, JSON_UNESCAPED_UNICODE);
                }
            }
            if (isset($responseMeta['clientEvents'])) {
                $resendRequestData['clientEvents'] = $responseMeta['clientEvents'];
                $resend = true;
            }
            if (isset($responseMeta['currentUser'])) {
                $currentUserData = $responseMeta['currentUser'];
                $app->data->set($app->data->make('.temp/bearcms/userkeys/' . md5($currentUserData['key']), $currentUserData['id']));
            }
            $responseBody = null;
            if (isset($responseMeta['clientEvents'])) {
                $responseBody = $response['body']; // Can be changed in a command
            }
            if ($resend) {
                $response = $send($resendRequestData, $counter + 1);
            }
            if (isset($responseMeta['clientEvents']) && !empty($responseBody) > 0) {
                $response['bodyPrefix'] = $responseBody;
            }
            return $response;
        };
        $response = $send();
        if ($sendCookies) {
            Cookies::setList(Cookies::TYPE_SERVER, Cookies::parseServerCookies($response['header']));
        }
        return $response;
    }

}
