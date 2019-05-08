<?php
/*
 * Bear CMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) Amplilabs Ltd.
 * Free to use under the MIT license.
 */

use BearFramework\App;

$app = App::get();
$context = $app->contexts->get(__FILE__);

$count = strlen($component->count) > 0 ? (int) $component->count : 5;
if ($count < 1) {
    $count = 1;
}
$threadID = $component->threadID;
$content = '';
if (strlen($threadID) > 0) {
    $content .= '<div class="bearcms-comments-element">';
    $content .= '<component src="file:' . $context->dir . '/components/bearcmsCommentsElement/commentsList.php" count="' . htmlentities($count) . '" threadID="' . htmlentities($threadID) . '" />';
    $content .= '<component src="form" filename="' . $context->dir . '/components/bearcmsCommentsElement/commentsForm.php" count="' . htmlentities($count) . '" threadID="' . htmlentities($threadID) . '" />';
    $content .= '</div>';
}
?><html>
    <head>
        <link rel="client-shortcuts-embed" name="-bearcms-comments-element">
        <style>
            .bearcms-comments-comment{display:block;clear:both;zoom:1;word-wrap:break-word;}
            .bearcms-comments-comment:after{visibility:hidden;display:block;font-size:0;content:" ";clear:both;height:0;}
            .bearcms-comments-comment-author-image{display:inline-block;float:left;}
            .bearcms-comments-comment-date{float:right;}
        </style>
    </head>
    <body><?= $content ?></body>
</html>