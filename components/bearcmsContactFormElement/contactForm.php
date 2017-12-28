<?php
/*
 * Bear CMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) 2016 Amplilabs Ltd.
 * Free to use under the MIT license.
 */

use \BearFramework\App;

$app = App::get();
$context = $app->context->get(__FILE__);

$form->constraints->setRequired('email');
$form->constraints->setEmail('email');

$form->constraints->setRequired('message');
$form->constraints->setMinLength('message', 2);

$form->onSubmit = function($values) use ($app, $component) {
    $recipients = explode(';', $component->email);
    $replyToEmail = strtolower($values['email']);
    foreach ($recipients as $recipient) {
        $recipient = trim($recipient);
        $defaultEmailSender = \BearCMS\Internal\Options::$defaultEmailSender;
        if (!is_array($defaultEmailSender)) {
            throw new \Exception('The defaultEmailSender option is empty.');
        }
        $email = $app->emails->make();
        $email->sender->email = $defaultEmailSender['email'];
        $email->sender->name = $defaultEmailSender['name'];
        $email->subject = sprintf(__('bearcms.contactForm.Message in %s'), $app->request->host);
        $email->content->add(sprintf(__('bearcms.contactForm.Message from %s'), $replyToEmail) . "\n\n" . $values['message']);
        $email->recipients->add($recipient);
        $email->replyToRecipients->add($replyToEmail);
        $app->emails->send($email);
    }

    return [
        'success' => 1,
        'message' => __('bearcms.contactForm.SuccessfullySent')
    ];
};
?><html>
    <head>
        <style>
            .bearcms-contact-form-element-message{
                display: block;
                resize: none;
            }
            .bearcms-contact-form-element-send-button{
                display: inline-block;
                cursor: pointer;
            }
        </style>
    </head>
    <body><?php
        echo '<form onrequestsent="bearCMS.contactFormElement.onRequestSent(event);" onresponsereceived="bearCMS.contactFormElement.onResponseReceived(event);" onsubmitdone="bearCMS.contactFormElement.onSubmitDone(event);">';
        echo '<label class="bearcms-contact-form-element-email-label">' . __('bearcms.contactForm.Email') . '</label>';
        echo '<input type="text" name="email" class="bearcms-contact-form-element-email"/>';
        echo '<label class="bearcms-contact-form-element-message-label">' . __('bearcms.contactForm.Message') . '</label>';
        echo '<textarea name="message" class="bearcms-contact-form-element-message"></textarea>';
        echo '<span onclick="this.parentNode.submit();" class="bearcms-contact-form-element-send-button">' . __('bearcms.contactForm.Send') . '</span>';
        echo '<span style="display:none;" class="bearcms-contact-form-element-send-button bearcms-contact-form-element-send-button-waiting">' . __('bearcms.contactForm.Sending ...') . '</span>';
        echo '</form>';
        ?></body>
</html>