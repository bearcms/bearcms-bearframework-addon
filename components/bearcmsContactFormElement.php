<?php
/*
 * Bear CMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) 2016 Amplilabs Ltd.
 * Free to use under the MIT license.
 */

use \BearFramework\App;

$email = strlen($component->email) > 0 ? $component->email : '';

$app = App::get();
$context = $app->context->get(__FILE__);

$content = '';
$content .= '<component src="form" filename="' . $context->dir . '/components/bearcmsContactFormElement/contactForm.php" email="' . htmlentities($email) . '" />';
$content .= '<script id="bearcms-bearframework-addon-script-6" src="' . htmlentities($context->assets->getUrl('components/bearcmsContactFormElement/assets/contactFormElement.js', ['cacheMaxAge' => 999999, 'version' => 1])) . '" async></script>';

?><html>
    <body><?= $content ?></body>
</html>