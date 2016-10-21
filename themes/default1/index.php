<?php

use BearFramework\App;

$app = App::get();
$context = $app->getContext(__FILE__);

$context->assets->addDir('themes/default1/assets');

$app->hooks->add('responseCreated', function($response) use ($app, $context) {
    if (!empty($response->applyBearCMSTemplate)) {
        $templateContent = null;
        $hookName = null;
        if ($response instanceof App\Response\HTML) {
            $templateContent = '<component src="file:' . $context->dir . '/themes/default1/components/defaultTemplate.php"/>';
            $hookName = 'bearCMSTheme1DefaultTemplateCreated';
        } elseif ($response instanceof App\Response\NotFound) {
            $templateContent = '<component src="file:' . $context->dir . '/themes/default1/components/defaultTemplate.php" mode="notFound"/>';
            $hookName = 'bearCMSTheme1NotFoundTemplateCreated';
        } elseif ($response instanceof App\Response\TemporaryUnavailable) {
            $templateContent = '<component src="file:' . $context->dir . '/themes/default1/components/defaultTemplate.php" mode="temporaryUnavailable"/>';
            $hookName = 'bearCMSTheme1TemporaryUnavailableTemplateCreated';
        }

        if ($templateContent !== null) {
            $templateContent = $app->components->process($templateContent, ['recursive' => false]);
            $object = new ArrayObject();
            $object->content = $templateContent;
            $app->hooks->execute($hookName, $object);
            $templateContent = $app->components->process($object->content);

            $template = new BearFramework\HTMLTemplate($templateContent);
            $template->insert('body', $response->content);
            $response->content = $template->getResult();
        }
    }
});
