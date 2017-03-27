<?php

/*
 * Bear CMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) 2016 Amplilabs Ltd.
 * Free to use under the MIT license.
 */

namespace BearCMS\Internal;

use BearFramework\App;

class TempClientData
{

    static function get($key)
    {
        $app = App::get();
        if (preg_match('/^[a-f0-9]{32}$/', $key) !== 1) {
            return false;
        }
        $tempData = \BearCMS\Internal\Data::getValue('.temp/clientdata/' . $key);
        $data = null;
        if ($tempData !== null) {
            $data = json_decode($tempData, true);
        }
        if (is_array($data) && isset($data['v'])) {
            return $data['v'];
        }
        return false;
    }

    static function set($data)
    {
        $app = App::get();
        $encodedData = json_encode(['v' => $data]);
        $key = md5($encodedData);
        $dataKey = '.temp/clientdata/' . $key;
        if (!$app->data->exists($dataKey)) {
            $app->data->set($app->data->make($dataKey, $encodedData));
            \BearCMS\Internal\Data::setChanged($dataKey);
        }
        return $key;
    }

}
