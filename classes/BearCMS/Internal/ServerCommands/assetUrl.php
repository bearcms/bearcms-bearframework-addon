<?php

/*
 * BearCMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) Amplilabs Ltd.
 * Free to use under the MIT license.
 */

use BearFramework\App;

return function($data) {
    $app = App::get();
    if (!isset($data['filename'])) {
        throw new Exception('');
    }
    if (!isset($data['options'])) {
        throw new Exception('');
    }
    return $app->assets->getUrl($data['filename'], $data['options']);
};
