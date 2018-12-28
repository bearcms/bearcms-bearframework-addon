<?php

/*
 * Bear CMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) Amplilabs Ltd.
 * Free to use under the MIT license.
 */

namespace BearCMS\Internal;

use BearFramework\App;
use BearCMS\Internal;
use BearCMS\Internal\Config;
use BearCMS\Internal2;

/**
 * @internal
 */
class ServerCommands
{

    /**
     * 
     * @return array
     */
    static function about(): array
    {
        $result = [];
        if (strlen(Config::$appSecretKey) > 0) {
            $temp = explode('-', Config::$appSecretKey);
            $result['appID'] = $temp[0];
        }
        $result['phpVersion'] = phpversion();
        return $result;
    }

    /**
     * 
     * @param array $data
     * @return array
     */
    static function addonAdd(array $data): array
    {
        try {
            Internal\Data\Addons::add($data['id']);
            if ($data['enabled'] !== null) {
                if ($data['enabled']) {
                    Internal\Data\Addons::enable($data['id']);
                } else {
                    Internal\Data\Addons::disable($data['id']);
                }
            }
            return [];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 
     * @param array $data
     * @return string
     */
    static function addonAssetUrl(array $data): string
    {
        $app = App::get();
        $addonDir = \BearFramework\Addons::get($data['addonID'])->dir;
        return $app->assets->getUrl($addonDir . '/' . $data['key'], $data['options']);
    }

    /**
     * 
     * @param array $data
     * @return void
     */
    static function addonDelete(array $data): void
    {
        Internal\Data\Addons::delete($data['id']);
    }

    /**
     * 
     * @param array $data
     * @return void
     */
    static function addonDisable(array $data): void
    {
        Internal\Data\Addons::disable($data['id']);
    }

    /**
     * 
     * @param array $data
     * @return void
     */
    static function addonEnable(array $data): void
    {
        Internal\Data\Addons::enable($data['id']);
    }

    /**
     * 
     * @param array $data
     * @return array|null
     */
    static function addonGet(array $data): ?array
    {
        $addon = Internal\Data\Addons::get($data['id']);
        if ($addon !== null) {
            return $addon->toArray();
        }
        return null;
    }

    /**
     * 
     * @param array $data
     * @return void
     */
    static function addonSetOptions(array $data): void
    {
        Internal\Data\Addons::setOptions($data['id'], $data['options']);
        if ($data['enabled'] !== null) {
            if ($data['enabled']) {
                Internal\Data\Addons::enable($data['id']);
            } else {
                Internal\Data\Addons::disable($data['id']);
            }
        }
    }

    /**
     * 
     * @return array
     */
    static function addonsList(): array
    {
        return Internal\Data\Addons::getList()->toArray();
    }

    /**
     * 
     * @param array $data
     * @return string
     */
    static function appAssetUrl(array $data): string
    {
        $app = App::get();
        return $app->assets->getUrl($app->config->appDir . DIRECTORY_SEPARATOR . $data['key'], $data['options']);
    }

    /**
     * 
     * @param array $data
     * @return string
     */
    static function assetUrl(array $data): string
    {
        $app = App::get();
        return $app->assets->getUrl($data['filename'], $data['options']);
    }

    /**
     * 
     * @return array
     */
    static function blogCategories(): array
    {
        $list = Internal\Data::getList('bearcms/blog/categories/category/');
        $structure = Internal\Data::getValue('bearcms/blog/categories/structure.json');
        $temp = [];
        $temp['structure'] = $structure !== null ? json_decode($structure, true) : [];
        $temp['categories'] = [];
        foreach ($list as $value) {
            $temp['categories'][] = json_decode($value, true);
        }
        return $temp;
    }

    /**
     * 
     * @return array
     */
    static function blogPostsList(): array
    {
        $app = App::get();
        return $app->bearCMS->data->blogPosts->getList()->toArray();
    }

    /**
     * 
     * @param array $data
     * @return array
     */
    static function checkpoint(array $data): array
    {
        return $data;
    }

    /**
     * 
     * @param array $data
     * @return void
     */
    static function commentDelete(array $data): void
    {
        Internal\Data\Comments::deleteCommentForever($data['threadID'], $data['commentID']);
    }

    /**
     * 
     * @param array $data
     * @return void
     */
    static function commentSetStatus(array $data): void
    {
        Internal\Data\Comments::setStatus($data['threadID'], $data['commentID'], $data['status']);
    }

    /**
     * 
     * @param array $data
     * @return int
     */
    static function commentsCount(array $data): int
    {
        $result = Internal2::$data2->comments->getList();
        if ($data['type'] !== 'all') {
            $result->filterBy('status', $data['type']);
        }
        return $result->length;
    }

    /**
     * 
     * @param array $data
     * @return array
     */
    static function commentsList(array $data): array
    {
        $result = Internal2::$data2->comments->getList();
        $result->sortBy('createdTime', 'desc');
        if ($data['type'] !== 'all') {
            $result->filterBy('status', $data['type']);
        }
        $result = $result->slice($data['limit'] * ($data['page'] - 1), $data['limit']);
        $locations = Internal\Data\Comments::getCommentsElementsLocations();
        foreach ($result as $i => $item) {
            if (isset($locations[$item->threadID])) {
                $result[$i]->location = $locations[$item->threadID];
            } else {
                $result[$i]->location = '';
            }
            $result[$i]->author = Internal\PublicProfile::getFromAuthor($item->author)->toArray();
        }
        return $result->toArray();
    }

    /**
     * 
     * @param array $data
     * @return array
     */
    static function data(array $data): array
    {
        $result = [];
        $app = App::get();

        $validateKey = function($key) {
            if (strpos($key, 'bearcms/') !== 0 && strpos($key, '.temp/bearcms/') !== 0 && strpos($key, '.recyclebin/bearcms/') !== 0) {
                throw new \Exception('The key ' . $key . ' is forbidden!');
            }
        };

        foreach ($data as $commandData) {
            $command = $commandData['command'];
            $commandResult = [];
            if ($command === 'get') {
                $validateKey($commandData['key']);
                $value = $app->data->getValue($commandData['key']);
                $commandResult['schemaVersion'] = 2;
                if ($value !== null) {
                    $commandResult['result'] = ['exists' => true, 'value' => $value];
                } else {
                    $commandResult['result'] = ['exists' => false];
                }
            } elseif ($command === 'set') {
                $validateKey($commandData['key']);
                $app->data->set($app->data->make($commandData['key'], $commandData['body']));
                Internal\Data::setChanged($commandData['key']);
            } elseif ($command === 'delete') {
                $validateKey($commandData['key']);
                if ($app->data->exists($commandData['key'])) {
                    $app->data->delete($commandData['key']);
                }
            } elseif ($command === 'rename') {
                $validateKey($commandData['sourceKey']);
                $validateKey($commandData['targetKey']);
                $app->data->rename($commandData['sourceKey'], $commandData['targetKey']);
            } elseif ($command === 'makePublic') {
                $validateKey($commandData['key']);
                $app->data->makePublic($commandData['key']);
            } elseif ($command === 'makePrivate') {
                $validateKey($commandData['key']);
                $app->data->makePrivate($commandData['key']);
            }
            $result[] = $commandResult;
        }
        return $result;
    }

    /**
     * 
     * @param array $data
     * @return int
     */
    static function dataFileSize(array $data): int
    {
        $app = App::get();
        $filename = $app->data->getFilename($data['key']);
        if (is_file($filename)) {
            return filesize($filename);
        }
        return 0;
    }

    /**
     * 
     * @param array $data
     * @return array
     */
    static function dataSchema(array $data): array
    {
        $app = App::get();
        $dataSchema = new Internal\DataSchema($data['id']);
        $app->hooks->execute('bearCMSDataSchemaRequested', $dataSchema);
        return $dataSchema->fields;
    }

    /**
     * 
     * @param array $data
     * @return string
     */
    static function dataUrl(array $data): string
    {
        $app = App::get();
        return $app->assets->getUrl($app->data->getFilename($data['key']), $data['options']);
    }

    /**
     * 
     * @param array $data
     * @return void
     */
    static function elementDelete(array $data): void
    {
        $app = App::get();
        $elementID = $data['id'];
        $rawDataList = Internal\ElementsHelper::getElementsRawData([$elementID]);
        if ($rawDataList[$elementID] !== null) {
            $elementData = json_decode($rawDataList[$elementID], true);
            $app->data->delete('bearcms/elements/element/' . md5($elementID) . '.json');
            if (isset($elementData['type'])) {
                $componentName = array_search($elementData['type'], Internal\ElementsHelper::$elementsTypesCodes);
                if ($componentName !== false) {
                    $options = Internal\ElementsHelper::$elementsTypesOptions[$componentName];
                    if (isset($options['onDelete']) && is_callable($options['onDelete'])) {
                        call_user_func($options['onDelete'], isset($elementData['data']) ? $elementData['data'] : []);
                    }
                }
            }
        }
    }

    /**
     * 
     * @param array $data
     * @param \ArrayObject $response
     * @return void
     * @throws Exception
     */
    static function elementsEditor(array $data, \ArrayObject $response): void
    {
        if (!empty(Internal\ElementsHelper::$editorData)) {
            $requestArguments = [];
            $requestArguments['data'] = json_encode(Internal\ElementsHelper::$editorData);
            $requestArguments['jsMode'] = 1;
            $elementsEditorData = Internal\Server::call('elementseditor', $requestArguments, true);
            if (is_array($elementsEditorData) && isset($elementsEditorData['result'], $elementsEditorData['result']['content'])) {
                $response['value'] = Internal\Server::mergeAjaxResponses($response['value'], json_decode($elementsEditorData['result']['content'], true));
                $response['value'] = Internal\Server::updateAssetsUrls($response['value'], true);
            } else {
                throw new Exception('');
            }
        }
    }

    /**
     * 
     * @param array $data
     * @param \ArrayObject $response
     * @return void
     */
    static function evalHTML(array $data, \ArrayObject $response): void
    {
        $response1 = $response['value'];
        $response2 = ['js' => 'var e=document.querySelector(\'#' . $data['elementID'] . '\');if(e){html5DOMDocument.evalElement(e);}'];
        $response['value'] = Internal\Server::mergeAjaxResponses($response1, $response2);
    }

    /**
     * 
     * @param array $data
     * @return array|null
     */
    static function file(array $data): ?array
    {
        $app = App::get();
        $item = $app->data->get('bearcms/files/custom/' . $data['filename']);
        if ($item !== null) {
            $key = $item->key;
            $fullFilename = $app->data->getFilename($key);
            $result = [
                'filename' => str_replace('bearcms/files/custom/', '', $key),
                'name' => (isset($item->metadata->name) ? $item->metadata->name : str_replace('bearcms/files/custom/', '', $key)),
                'published' => (isset($item->metadata->published) ? (int) $item->metadata->published : 0),
                'size' => filesize($fullFilename),
                'dateUploaded' => filemtime($fullFilename)
            ];
            return $result;
        }
        return null;
    }

    /**
     * 
     * @param array $data
     * @return void
     * @throws Exception
     */
    static function fileSet(array $data): void
    {
        $app = App::get();
        $fileData = $data['data'];
        $currentFileData = self::file(['filename' => $data['filename']]);
        if (isset($fileData['name']) && $currentFileData['name'] !== $fileData['name']) {
            $updateKey = function($key) {
                $originalKey = $key;
                $key = preg_replace('/[^a-z0-9\.\-\_]+/u', '-', strtolower($key));
                while (strpos($key, '--') !== false) {
                    $key = str_replace('--', '-', $key);
                }
                $key = trim($key, '-');
                $info = pathinfo($key);
                $info['filename'] = trim($info['filename'], '-');
                if (strlen($info['filename']) === 0) {
                    $info['filename'] = md5($originalKey);
                }
                if (strlen($key) > 80) {
                    $info['filename'] = substr($info['filename'], 0, 80);
                }
                $key = $info['filename'] . (isset($info['extension']) ? '.' . $info['extension'] : '');
                return $key;
            };
            $sourceKey = 'bearcms/files/custom/' . $updateKey($data['filename']);
            $targetKey = 'bearcms/files/custom/' . $updateKey($fileData['name']);
            if ($sourceKey !== $targetKey && is_file($app->data->getFilename($sourceKey))) {
                if (is_file($app->data->getFilename($targetKey))) {
                    $info = pathinfo($targetKey);
                    if (isset($info['extension'])) {
                        $targetKeyPrefix = substr($targetKey, 0, strlen($targetKey) - strlen($info['extension']) - 1);
                    } else {
                        $targetKeyPrefix = $targetKey;
                    }
                    $done = false;
                    for ($i = 1; $i < 9999999; $i++) {
                        $tempTargetKey = $targetKeyPrefix . '_' . $i . (isset($info['extension']) ? '.' . $info['extension'] : '');
                        if (!is_file($app->data->getFilename($tempTargetKey))) {
                            $targetKey = $tempTargetKey;
                            $done = true;
                            break;
                        }
                    }
                    if (!$done) {
                        throw new Exception('Cannot find available filename for ' . $targetKey);
                    }
                }
                $app->data->rename($sourceKey, $targetKey);
                $data['filename'] = str_replace('bearcms/files/custom/', '', $targetKey);
            }
        }
        $key = 'bearcms/files/custom/' . $data['filename'];
        if (isset($fileData['name'])) {
            $app->data->setMetadata($key, 'name', (string) $fileData['name']);
        }
        if (isset($fileData['published'])) {
            $app->data->setMetadata($key, 'published', (string) $fileData['published']);
        }
    }

    /**
     * 
     * @return array
     */
    static function files(): array
    {
        $app = App::get();
        $result = $app->data->getList()
                ->filterBy('key', 'bearcms/files/custom/', 'startWith');
        $temp = [];
        foreach ($result as $item) {
            $key = $item->key;
            $temp[] = [
                'filename' => str_replace('bearcms/files/custom/', '', $key),
                'name' => (isset($item->metadata->name) ? $item->metadata->name : str_replace('bearcms/files/custom/', '', $key)),
                'published' => (isset($item->metadata->published) ? (int) $item->metadata->published : 0)
            ];
        }
        return $temp;
    }

    /**
     * 
     * @return array
     */
    static function forumCategories(): array
    {
        $list = Internal\Data::getList('bearcms/forums/categories/category/');
        $structure = Internal\Data::getValue('bearcms/forums/categories/structure.json');
        $temp = [];
        $temp['structure'] = $structure !== null ? json_decode($structure, true) : [];
        $temp['categories'] = [];
        foreach ($list as $value) {
            $temp['categories'][] = json_decode($value, true);
        }
        return $temp;
    }

    /**
     * 
     * @param array $data
     * @return array
     */
    static function forumPostGet(array $data): array
    {
        $result = Internal2::$data2->forumPosts->get($data['forumPostID']);
        $result->author = Internal\PublicProfile::getFromAuthor($result->author)->toArray();
        $result->replies = new \IvoPetkov\DataList();
        return $result->toArray();
    }

    /**
     * 
     * @param array $data
     * @return void
     */
    static function forumPostReplyDelete(array $data): void
    {
        Internal\Data\ForumPostsReplies::deleteReplyForever($data['forumPostID'], $data['replyID']);
    }

    /**
     * 
     * @param array $data
     * @return void
     */
    static function forumPostReplySetStatus(array $data): void
    {
        Internal\Data\ForumPostsReplies::setStatus($data['forumPostID'], $data['replyID'], $data['status']);
    }

    /**
     * 
     * @param array $data
     * @return void
     */
    static function forumPostSetStatus(array $data): void
    {
        Internal\Data\ForumPosts::setStatus($data['forumPostID'], $data['status']);
    }

    /**
     * 
     * @param array $data
     * @return int
     */
    static function forumPostsCount(array $data): int
    {
        $result = Internal2::$data2->forumPosts->getList();
        if ($data['type'] !== 'all') {
            $result->filterBy('status', $data['type']);
        }
        return $result->length;
    }

    /**
     * 
     * @param array $data
     * @return array
     */
    static function forumPostsList(array $data): array
    {
        $result = Internal2::$data2->forumPosts->getList();
        $result->sortBy('createdTime', 'desc');
        if ($data['type'] !== 'all') {
            $result->filterBy('status', $data['type']);
        }
        $result = $result->slice($data['limit'] * ($data['page'] - 1), $data['limit']);
        foreach ($result as $i => $item) {
            $result[$i]->location = '';
            $result[$i]->author = Internal\PublicProfile::getFromAuthor($item->author)->toArray();
        }
        return $result->toArray();
    }

    /**
     * 
     * @param array $data
     * @return int
     * @throws Exception
     */
    static function forumPostsRepliesCount(array $data): int
    {
        $result = Internal2::$data2->forumPostsReplies->getList();
        if (isset($data['forumPostID']) && strlen($data['forumPostID']) > 0) {
            $result->filterBy('forumPostID', $data['forumPostID']);
        }
        if ($data['type'] !== 'all') {
            $result->filterBy('status', $data['type']);
        }
        return $result->length;
    }

    /**
     * 
     * @param array $data
     * @return array
     * @throws Exception
     */
    static function forumPostsRepliesList(array $data): array
    {
        $result = Internal2::$data2->forumPostsReplies->getList();
        $result->sortBy('createdTime', 'desc');
        if (isset($data['forumPostID']) && strlen($data['forumPostID']) > 0) {
            $result->filterBy('forumPostID', $data['forumPostID']);
        }
        if ($data['type'] !== 'all') {
            $result->filterBy('status', $data['type']);
        }
        $result = $result->slice($data['limit'] * ($data['page'] - 1), $data['limit']);
        foreach ($result as $i => $item) {
            $result[$i]->location = '';
            $result[$i]->author = Internal\PublicProfile::getFromAuthor($item->author)->toArray();
        }
        return $result->toArray();
    }

    /**
     * 
     * @return void
     */
    static function iconChanged(): void
    {
        Internal\Cookies::setList(Internal\Cookies::TYPE_CLIENT, [['name' => 'fc', 'value' => uniqid(), 'expire' => time() + 86400 + 1000]]);
    }

    /**
     * 
     * @return array
     */
    static function pagesList(): array
    {
        $list = Internal\Data::getList('bearcms/pages/page/');
        $structure = Internal\Data::getValue('bearcms/pages/structure.json');
        $temp = [];
        $temp['structure'] = $structure !== null ? json_decode($structure, true) : [];
        $temp['pages'] = [];
        foreach ($list as $value) {
            $temp['pages'][] = json_decode($value, true);
        }
        return $temp;
    }

    /**
     * 
     * @param array $data
     * @param \ArrayObject $response
     * @return void
     */
    static function replaceContent(array $data, \ArrayObject $response): void
    {
        $app = App::get();
        $value = json_encode($response['value']);
        $content = $app->components->process($data['content']);
        $domDocument = new \IvoPetkov\HTML5DOMDocument();
        $domDocument->loadHTML($content);
        $bodyElement = $domDocument->querySelector('body');
        $content = $bodyElement->innerHTML;
        $bodyElement->parentNode->removeChild($bodyElement);
        $allButBody = $domDocument->saveHTML();
        $startPosition = strpos($value, '{bearcms-replace-content-' . $data['id'] . '-');
        if ($startPosition === false) {
            return;
        }

        $endPosition = strpos($value, '}', $startPosition);

        $modificationsString = substr($value, $startPosition + 58, $endPosition - $startPosition - 58);
        $parts = explode('\'', $modificationsString);
        $singleQuoteSlashesCount = strlen($parts[0]);
        $doubleQuoteSlashesCount = strlen($parts[1]) - 1;
        for ($i = 0; $i < $doubleQuoteSlashesCount; $i += 2) {
            $content = substr(json_encode($content), 1, -1);
        }
        for ($i = 0; $i < $singleQuoteSlashesCount; $i += 2) {
            $content = addslashes($content);
        }
        $value = str_replace(substr($value, $startPosition, $endPosition - $startPosition + 1), $content, $value);
        //todo optimize
        $response1 = ['js' => 'html5DOMDocument.insert(' . json_encode($allButBody, true) . ');'];
        $response2 = json_decode($value, true);
        $response['value'] = Internal\Server::mergeAjaxResponses($response1, $response2);
    }

    /**
     * 
     * @return array
     */
    static function settingsGet(): array
    {
        $app = App::get();
        return $app->bearCMS->data->settings->get()->toArray();
    }

    /**
     * 
     * @param array $data
     * @param \ArrayObject $response
     */
    static function temporaryRedirect(array $data, \ArrayObject $response)
    {
        $app = App::get();
        Internal\Cookies::setList(Internal\Cookies::TYPE_SERVER, Internal\Cookies::parseServerCookies($response['headers']));
        $response = new App\Response\TemporaryRedirect($data['url']);
        Internal\Cookies::apply($response);
        $app->respond($response);
        exit;
    }

    /**
     * 
     * @param array $data
     * @return void
     */
    static function themeApplyUserValues(array $data): void
    {
        $themeID = $data['id'];
        $userID = $data['userID'];
        Internal\Themes::applyUserValues($themeID, $userID);
    }

    /**
     * 
     * @param array $data
     * @return void
     */
    static function themeDiscardUserOptions(array $data): void
    {
        $themeID = $data['id'];
        $userID = $data['userID'];
        if (strlen($themeID) > 0 && strlen($userID) > 0) {
            Internal2::$data2->themes->discardUserOptions($themeID, $userID);
        }
    }

    /**
     * 
     * @param array $data
     * @return array
     */
    static function themeExport(array $data): array
    {
        $app = App::get();
        $themeID = $data['id'];
        $dataKey = Internal\Themes::export($themeID);
        $app->data->makePublic($dataKey);
        return ['downloadUrl' => $app->assets->getUrl($app->data->getFilename($dataKey), ['download' => true])];
    }

    /**
     * 
     * @param array $data
     * @return array
     */
    static function themeGet(array $data): ?array
    {
        $app = App::get();
        $themeID = $data['id'];
        $includeOptions = isset($data['includeOptions']) && !empty($data['includeOptions']);
        $themes = Internal\Themes::getIDs();
        foreach ($themes as $id) {
            if ($id === $themeID) {
                $optionsSchemaAsArray = Internal\Themes::getOptionsSchemaAsArray($id);
                $themeManifest = Internal\Themes::getManifest($id);
                $themeData = $themeManifest;
                $themeData['id'] = $id;
                $themeData['hasOptions'] = !empty($optionsSchemaAsArray);
                $themeData['hasStyles'] = sizeof(Internal\Themes::getStyles($id)) > 0;
                if ($includeOptions) {
                    $themeData['options'] = [
                        'definition' => $optionsSchemaAsArray
                    ];
                    $result = Internal\Data::getValue('bearcms/themes/theme/' . md5($id) . '.json');
                    if ($result !== null) {
                        $temp = json_decode($result, true);
                        $optionsValues = isset($temp['options']) ? $temp['options'] : [];
                    } else {
                        $optionsValues = [];
                    }
                    $themeData['options']['activeValues'] = $optionsValues;

                    $result = Internal\Data::getValue('.temp/bearcms/userthemeoptions/' . md5($app->bearCMS->currentUser->getID()) . '/' . md5($id) . '.json');
                    if ($result !== null) {
                        $temp = json_decode($result, true);
                        $optionsValues = isset($temp['options']) ? $temp['options'] : [];
                    } else {
                        $optionsValues = null;
                    }
                    $themeData['options']['currentUserValues'] = $optionsValues;
                }
                return $themeData;
            }
        }
        return null;
    }

    /**
     * 
     * @return string
     */
    static function themeGetActive(): string
    {
        return Internal\Themes::getActiveThemeID();
    }

    /**
     * 
     * @param array $data
     * @return array
     */
    static function themeImport(array $data): array
    {
        $sourceDataKey = $data['sourceDataKey'];
        $themeID = $data['id'];
        $userID = $data['userID'];
        try {
            Internal\Themes::import($sourceDataKey, $themeID, $userID);
            return ['status' => 'ok'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'errorCode' => $e->getCode()];
        }
    }

    /**
     * 
     * @param array $data
     * @return void
     */
    static function themeSetUserOptions(array $data): void
    {
        $themeID = $data['id'];
        $userID = $data['userID'];
        $values = $data['values'];
        Internal2::$data2->themes->setUserOptions($themeID, $userID, $values);
    }

    /**
     * 
     * @param array $data
     * @return array|null
     */
    static function themeStylesGet(array $data): ?array
    {
        $themeID = $data['id'];
        return Internal\Themes::getStyles($themeID, true);
    }

    /**
     * 
     * @return array
     */
    static function themesList(): array
    {
        $themes = Internal\Themes::getIDs();
        $result = [];
        foreach ($themes as $id) {
            $themeManifest = Internal\Themes::getManifest($id);
            $themeData = $themeManifest;
            $themeData['id'] = $id;
            $themeData['hasOptions'] = Internal\Themes::getOptionsSchema($id) !== null;
            $result[] = $themeData;
        }
        return $result;
    }

    /**
     * 
     * @param array $data
     * @return void
     */
    static function uploadsSizeAdd(array $data): void
    {
        Internal\Data\UploadsSize::add($data['key'], (int) $data['size']);
    }

    /**
     * 
     * @param array $data
     * @return void
     */
    static function uploadsSizeRemove(array $data): void
    {
        Internal\Data\UploadsSize::remove($data['key']);
    }

    /**
     * 
     * @param array $data
     * @return string|null
     */
    static function userIDByEmail(array $data): ?string
    {
        $app = App::get();
        $email = (string) $data['email'];
        $users = $app->bearCMS->data->users->getList();
        foreach ($users as $user) {
            if (array_search($email, $user->emails) !== false) {
                return $user->id;
            }
        }
        return null;
    }

    /**
     * 
     * @return array
     */
    static function usersIDs(): array
    {
        $app = App::get();
        $users = $app->bearCMS->data->users->getList();
        $result = [];
        foreach ($users as $user) {
            $result[] = $user->id;
        }
        return $result;
    }

    /**
     * 
     * @return array
     */
    static function usersInvitations(): array
    {
        $userInvitations = Internal2::$data2->usersInvitations->getList();
        $result = [];
        foreach ($userInvitations as $userInvitation) {
            $result[] = $userInvitation->toArray();
        }
        return $result;
    }

}
