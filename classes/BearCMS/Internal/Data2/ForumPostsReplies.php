<?php

/*
 * Bear CMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) Amplilabs Ltd.
 * Free to use under the MIT license.
 */

namespace BearCMS\Internal\Data2;

use BearFramework\App;

/**
 * @internal
 */
class ForumPostsReplies
{

    /**
     * Retrieves a list of all forum replies
     * 
     * @return \BearCMS\Internal\DataList List containing all forum replies data
     */
    public function getList()
    {
        $list = \BearCMS\Internal\Data::getList('bearcms/forums/posts/post/');

        $result = new \BearCMS\Internal\DataList();
        foreach ($list as $value) {
            $rawData = json_decode($value, true);
            if (isset($rawData['id'], $rawData['replies'])) {
                foreach ($rawData['replies'] as $replyData) {
                    $reply = new \BearCMS\Internal\Data2\ForumPostReply();
                    $reply->id = $replyData['id'];
                    $reply->status = $replyData['status'];
                    $reply->author = $replyData['author'];
                    $reply->text = $replyData['text'];
                    $reply->createdTime = $replyData['createdTime'];
                    $reply->forumPostID = $rawData['id'];
                    $result[] = $reply;
                }
            }
        }
        return $result;
    }

}