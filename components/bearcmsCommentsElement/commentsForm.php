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

$form->constraints->setRequired('cfcomment', 'Your comment cannot be empty!');

$form->onSubmit = function($values) use ($component, $app, $context) {
    $contextData = json_decode($values['cfcontext'], true);
    if (is_array($contextData) && isset($contextData['listElementID'], $contextData['listCommentsCount'])) {
        $listElementID = (string) $contextData['listElementID'];
        $listCommentsCount = (int) $contextData['listCommentsCount'];
    } else {
        $this->throwError();
    }
    if (!$app->currentUser->exists()) {
        $this->throwError();
    }

    $threadID = $component->threadID;
    $author = [
        'type' => 'user',
        'provider' => $app->currentUser->provider,
        'id' => $app->currentUser->id
    ];
    
    $data = new ArrayObject();
    $data->author = $author;
    $data->text = $values['cfcomment'];
    $data->cancel = false;
    $data->cancelMessage = '';
    $data->status = 'approved';
    $app->hooks->execute('bearCMSCommentAdd', $data);
    if($data->cancel){
        $this->throwError($data->cancelMessage);
    }
    \BearCMS\Internal\Data\Comments::add($threadID, $author, $values['cfcomment'], $data->status);

    $listContent = $app->components->process('<component src="file:' . $context->dir . '/components/bearcmsCommentsElement/commentsList.php" count="' . htmlentities($listCommentsCount) . '" threadID="' . htmlentities($threadID) . '" />');
    return [
        'listElementID' => $listElementID,
        'listContent' => $listContent,
        'success' => 1
    ];
};
?><html>
    <head>
        <style>
            .bearcms-comments-element-textarea{
                display:block;
                width:100%;
                resize: none;
                box-sizing: border-box;
                height:100px;
                padding:20px;

                border: 1px solid #ccc;
                padding: 15px;
                display: block;
                width: 100%;
                box-sizing: border-box;
                resize: none;
                font-size: 14px;
                color: #000;
                font-family: Arial;
                height: 200px;
                line-height: 180%;
            }
            .bearcms-comments-element-send-button{
                background-color:gray;
                display:inline-block;
                padding:10px;

                margin-top: 15px;
                cursor: pointer;
                display:none;
            }
        </style>
    </head>
    <body><?php
        echo '<form onbeforesubmit="bearCMS.commentsElement.onBeforeSubmitForm(event);" onsubmitdone="bearCMS.commentsElement.onSubmitFormDone(event);">';
        echo '<input type="hidden" name="cfcontext"/>';
        echo '<textarea name="cfcomment" class="bearcms-comments-element-textarea" onfocus="this.nextSibling.style.display=\'inline-block\';"></textarea>';
        echo '<span onclick="this.parentNode.submit();" href="javascript:void(0);" class="bearcms-comments-element-send-button">Send</span>';
        echo '<span style="display:none;" class="bearcms-comments-element-send-button bearcms-comments-element-send-button-waiting">Sending ...</span>';
        echo '</form>';
        ?></body>
</html>