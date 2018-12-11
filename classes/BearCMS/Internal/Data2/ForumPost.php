<?php

/*
 * Bear CMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) Amplilabs Ltd.
 * Free to use under the MIT license.
 */

namespace BearCMS\Internal\Data2;

/**
 * @property string $id
 * @property string $status
 * @property array $author
 * @property ?string $title
 * @property ?string $text
 * @property string $categoryID
 * @property ?int $createdTime
 * @property \IvoPetkov\DataList|\BearCMS\Internal\Data2\ForumPostReply[] $replies
 * @internal
 */
class ForumPost
{

    use \IvoPetkov\DataObjectTrait;
    use \IvoPetkov\DataObjectToArrayTrait;

    function __construct()
    {
        $this->defineProperty('id', [
            'type' => 'string'
        ]);
        $this->defineProperty('status', [
            'type' => 'string'
        ]);
        $this->defineProperty('author', [
            'type' => 'array'
        ]);
        $this->defineProperty('title', [
            'type' => '?string'
        ]);
        $this->defineProperty('text', [
            'type' => '?string'
        ]);
        $this->defineProperty('categoryID', [
            'type' => 'string'
        ]);
        $this->defineProperty('createdTime', [
            'type' => '?int'
        ]);
        $this->defineProperty('replies', [
            'type' => '\IvoPetkov\DataList',
            'init' => function() {
                return new \IvoPetkov\DataList();
            }
        ]);
    }

}
