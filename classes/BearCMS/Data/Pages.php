<?php

/*
 * Bear CMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) 2016 Amplilabs Ltd.
 * Free to use under the MIT license.
 */

namespace BearCMS\Data;

use BearFramework\App;

class Pages
{

    /**
     * 
     * @param string $id
     * @return array|null
     */
    static function getPage($id)
    {
        $app = App::$instance;
        $data = $app->data->get(
                [
                    'key' => 'bearcms/pages/page/' . md5($id) . '.json',
                    'result' => ['body']
                ]
        );
        if (isset($data['body'])) {
            return json_decode($data['body'], true);
        }
        return null;
    }

    /**
     * 
     * @return array
     */
    static function getList()
    {
        $app = App::$instance;
        $data = $app->data->search(
                [
                    'where' => [
                        ['key', 'bearcms/pages/page/', 'startsWith']
                    ],
                    'result' => ['body']
                ]
        );
        $result = [];
        foreach ($data as $item) {
            $result[] = json_decode($item['body'], true);
        }
        return $result;
    }

    /**
     * 
     * @return array
     */
    static function getStructure()
    {
        $app = App::$instance;
        $data = $app->data->get(
                [
                    'key' => 'bearcms/pages/structure.json',
                    'result' => ['body']
                ]
        );
        if (isset($data['body'])) {
            return json_decode($data['body'], true);
        }
        return [];
    }

}
