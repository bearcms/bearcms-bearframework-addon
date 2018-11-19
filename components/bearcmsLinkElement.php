<?php
/*
 * Bear CMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) Amplilabs Ltd.
 * Free to use under the MIT license.
 */

$url = $component->url;
$text = $component->text;
$title = $component->title;
$content = '<a title="' . htmlentities($title) . '" class="bearcms-link-element" href="' . htmlentities($url) . '">' . htmlspecialchars(isset($text{0}) ? $text : $url) . '</a>';

?><html>
    <body><?= $content ?></body>
</html>