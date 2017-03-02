<?php
/*
 * Bear CMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) 2016 Amplilabs Ltd.
 * Free to use under the MIT license.
 */

use \BearFramework\App;

$count = strlen($component->count) > 0 ? (int) $component->count : 5;
if ($count < 1) {
    $count = 1;
}
$threadID = $component->threadID;

$app = App::get();
$context = $app->context->get(__FILE__);

$content = '';
if (strlen($threadID) > 0) {
    $content .= '<component src="file:' . $context->dir . '/components/bearcmsCommentsElement/commentsList.php" count="' . htmlentities($count) . '" threadID="' . htmlentities($threadID) . '" />';
    $content .= '<component src="form" filename="' . $context->dir . '/components/bearcmsCommentsElement/commentsForm.php" count="' . htmlentities($count) . '" threadID="' . htmlentities($threadID) . '" />';
    $content .= '<script src="' . htmlentities($context->assets->getUrl('components/bearcmsCommentsElement/assets/commentsElement.js', ['cacheMaxAge' => 999999, 'version' => 1, 'robotsNoIndex' => true])) . '"></script>';
    $content .= '<script src="' . htmlentities($context->assets->getUrl('assets/HTML5DOMDocument.min.js')) . '"></script>';
}
?><html>
    <head>
        <style>
            .bearcms-comments-comment{
                clear:both;
                min-height:50px;
                margin-bottom:10px;
            }
            .bearcms-comments-comment-image{
                display:inline-block;
                width:50px;
                height:50px;
                float:left;
                margin-right:10px;
                margin-bottom:10px;
            }
        </style>
    </head>
    <body><?= $content ?></body>
</html>