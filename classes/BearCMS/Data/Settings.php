<?php

/*
 * Bear CMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) 2016 Amplilabs Ltd.
 * Free to use under the MIT license.
 */

namespace BearCMS\Data;

use BearFramework\App;

/**
 * Information about the site settings
 */
class Settings
{

    /**
     * Returns an array containing the site settings
     * 
     * @return \BearCMS\DataObject An object containing the site settings
     */
    public function get()
    {
        $app = App::$instance;
        $data = $app->data->get(
                [
                    'key' => 'bearcms/settings.json',
                    'result' => ['body']
                ]
        );
        if (isset($data['body'])) {
            $data = json_decode($data['body'], true);
        } else {
            $data = [];
        }
        if (!isset($data['title'])) {
            $data['title'] = '';
        }
        if (!isset($data['description'])) {
            $data['description'] = '';
        }
        if (!isset($data['keywords'])) {
            $data['keywords'] = '';
        }
        if (!isset($data['language'])) {
            $data['language'] = 'en';
        }
        if (!isset($data['icon'])) {
            $data['icon'] = '';
        }
        if (!isset($data['externalLinks'])) {
            $data['externalLinks'] = false;
        }
        if (!isset($data['allowSearchEngines'])) {
            $data['allowSearchEngines'] = false;
        }
        return new \BearCMS\DataObject($data);
    }

}
