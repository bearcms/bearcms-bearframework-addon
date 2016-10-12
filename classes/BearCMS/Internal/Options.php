<?php

/*
 * Bear CMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) 2016 Amplilabs Ltd.
 * Free to use under the MIT license.
 */

namespace BearCMS\Internal;

use BearFramework\App;

final class Options
{

    static $siteID = null;
    static $siteSecret = null;
    static $serverUrl = null;
    static $language = 'en';
    static $features = ['ALL'];
    static $cookiePrefix = null;
    static $logServerRequestsData = false;
    static $addonsDir = false;
    static $customPagesFields = [];
    static $customBlogFields = [];
    static $customSettingsFields = [];

    /**
     * 
     * @param array $data
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    static function set($data)
    {
        if (!is_array($data)) {
            throw new \InvalidArgumentException('');
        }

        if (isset($data['serverUrl']) && strlen($data['serverUrl']) > 0) {
            if (!isset($data['siteID']) || strlen($data['siteID']) === 0) {
                throw new \Exception('siteID option is not set for bearcms/bearframework-addon');
            }
            if (!isset($data['siteSecret']) || strlen($data['siteSecret']) === 0) {
                throw new \Exception('siteSecret option is not set for bearcms/bearframework-addon');
            }
            self::$serverUrl = $data['serverUrl'];
            self::$siteID = $data['siteID'];
            self::$siteSecret = $data['siteSecret'];
        }

        $app = App::$instance;

        if (isset($data['language'])) {
            self::$language = $data['language'];
        }

        if (isset($data['addonsDir'])) {
            $addonsDir = realpath($data['addonsDir']);
            if ($addonsDir === false) {
                throw new \Exception('addonsDir option is not value for bearcms/bearframework-addon');
            }
            self::$addonsDir = $addonsDir;
        }

        if (isset($data['features']) && is_array($data['features']) && !empty($data['features'])) {
            self::$features = $data['features'];
        }

        self::$cookiePrefix = substr(md5(md5($app->request->base) . md5(self::$serverUrl)), 0, 14) . '_bearcms_';

        if (isset($data['logServerRequestsData']) && $data['logServerRequestsData']) {
            self::$logServerRequestsData = true;
        }

        if (isset($data['customPagesFields']) && is_array($data['customPagesFields'])) {
            self::$customPagesFields = $data['customPagesFields'];
        }

        if (isset($data['customBlogFields']) && is_array($data['customBlogFields'])) {
            self::$customBlogFields = $data['customBlogFields'];
        }

        if (isset($data['customSettingsFields']) && is_array($data['customSettingsFields'])) {
            self::$customSettingsFields = $data['customSettingsFields'];
        }
    }

    static function hasServer()
    {
        return self::$serverUrl !== null;
    }

    static function hasFeature($name)
    {
        if (substr($name, -1) === '*') {
            $prefix = substr($name, 0, -1);
            foreach (self::$features as $feature) {
                if (strpos($feature, $prefix) === 0) {
                    return true;
                }
            }
        }
        return array_search($name, self::$features) !== false || (sizeof(self::$features) === 1 && self::$features[0] === 'ALL');
    }

}
