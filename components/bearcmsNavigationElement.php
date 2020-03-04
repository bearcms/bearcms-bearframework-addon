<?php
/*
 * Bear CMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) Amplilabs Ltd.
 * Free to use under the MIT license.
 */

use BearFramework\App;
use IvoPetkov\HTML5DOMDocument;

$app = App::get();
$appURLs = $app->urls;

$selectedPath = '';
if (strlen($component->selectedPath) > 0) {
    $selectedPath = $component->selectedPath;
}

$source = 'topPages';
if (strlen($component->source) > 0 && array_search($component->source, ['allPages', 'pageChildren', 'topPages', 'pageAllChildren']) !== false) {
    $source = $component->source;
}

$showHomeLink = false;
if ($source === 'pageChildren' || $source === 'pageAllChildren') {
    $sourceParentPageID = (string) $component->sourceParentPageID;
} elseif ($source === 'allPages' || $source === 'topPages') {
    $showHomeLink = $component->showHomeLink === 'true';
    $homeLinkText = strlen($component->homeLinkText) > 0 ? $component->homeLinkText : __('bearcms.navigation.home');
}

$itemsType = (string) $component->itemsType === 'onlySelected' ? 'onlySelected' : 'allExcept';
$items = strlen($component->items) > 0 ? explode(';', $component->items) : [];
if ($itemsType === 'onlySelected' && $showHomeLink) {
    $items[] = '_home';
}

$buildTree = function ($pages, $recursive = false, $level = 0) use ($appURLs, $selectedPath, &$buildTree, $itemsType, $items) {
    $itemsHtml = [];
    foreach ($pages as $page) {
        if ($page->status !== 'published') { //needed for the children
            continue;
        }
        $pageID = $page->id;
        if ($itemsType === 'allExcept' && array_search($pageID, $items) !== false) {
            continue;
        }
        if ($itemsType === 'onlySelected' && array_search($pageID, $items) === false) {
            continue;
        }
        $pagePath = $page->path;
        $classNames = 'bearcms-navigation-element-item';
        if ($pagePath === $selectedPath) {
            $classNames .= ' bearcms-navigation-element-item-selected';
        } elseif ($pageID !== '_home' && strpos($selectedPath, $pagePath) === 0) {
            $classNames .= ' bearcms-navigation-element-item-in-path';
        }
        $itemsHtml[] = '<li class="' . $classNames . '"><a href="' . htmlentities($appURLs->get($pagePath)) . '">' . htmlspecialchars($page->name) . '</a>';
        if ($recursive && isset($page->children)) {
            $itemsHtml[] = $buildTree($page->children, true, $level + 1);
        }
        $itemsHtml[] = '</li>';
    }
    if (empty($itemsHtml)) {
        return '';
    }

    if ($level === 0) {
        $attributes = ' class="bearcms-navigation-element"';
    } else {
        $attributes = ' class="bearcms-navigation-element-item-children"';
    }
    return '<ul' . $attributes . '>' . implode('', $itemsHtml) . '</ul>';
};

$menuType = 'list-vertical';
if (strlen($component->menuType) > 0) {
    if (array_search($component->menuType, ['horizontal-down', 'vertical-left', 'vertical-right', 'list-vertical']) !== false) {
        $menuType = $component->menuType;
    }
}

$pages = null;
if ($source === 'topPages' || $source === 'allPages') {
    $pages = \BearCMS\Internal\Data\Pages::getChildrenList(null); // Used instead of $app->bearCMS->data->pages->getList() for better performance
    $pages->filterBy('status', 'published');
} elseif ($source === 'pageChildren' || $source === 'pageAllChildren') {
    $pages = \BearCMS\Internal\Data\Pages::getChildrenList($sourceParentPageID); // Used instead of $app->bearCMS->data->pages->getList() for better performance
    $pages->filterBy('status', 'published');
}

$attributes = '';
$attributes .= ' type="' . $menuType . '"';
if (strlen($component->class) > 0) {
    $attributes .= ' class="' . htmlentities($component->class) . '"';
}
$attributes .= ' moreItemHtml="' . htmlentities('<li class="bearcms-navigation-element-item bearcms-navigation-element-item-more"><a></a><ul class="bearcms-navigation-element-item-children"></ul></li>') . '"';

$dataResponsiveAttributes = $component->getAttribute('data-responsive-attributes');
if (strlen($dataResponsiveAttributes) > 0) {
    $attributes .= ' data-responsive-attributes="' . htmlentities(str_replace('=>menuType=', '=>type=', $dataResponsiveAttributes)) . '"';
}

if ($pages !== null && $showHomeLink) {
    $pages->unshift(\BearCMS\Data\Pages\Page::fromArray(['id' => '_home', 'path' => '/', 'name' => $homeLinkText, 'parentID' => null, 'status' => 'published']));
}

$itemsHtml = (string) $component->innerHTML;
if (isset($itemsHtml[0])) {
    $domDocument = new HTML5DOMDocument();
    $domDocument->loadHTML($itemsHtml, HTML5DOMDocument::ALLOW_DUPLICATE_IDS);
    $ulElements = $domDocument->querySelectorAll('ul');
    foreach ($ulElements as $index => $ulElement) {
        $ulElement->setAttribute('class', trim($ulElement->getAttribute('class') . ' ' . ($index === 0 ? 'bearcms-navigation-element' : 'bearcms-navigation-element-item-children')));
    }
    $liElements = $domDocument->querySelectorAll('li');
    $requestBase = $app->request->base;
    foreach ($liElements as $index => $liElement) {
        $liClasssName = 'bearcms-navigation-element-item';
        if ($liElement->firstChild) {
            $liPath = str_replace($requestBase, '', $liElement->firstChild->getAttribute('href'));
            if ($liPath === $selectedPath) {
                $liClasssName .= ' bearcms-navigation-element-item-selected';
            } elseif ($liPath !== '/' && strpos($selectedPath, $liPath) === 0) {
                $liClasssName .= ' bearcms-navigation-element-item-in-path';
            }
        }
        $liElement->setAttribute('class', trim($liElement->getAttribute('class') . ' ' . $liClasssName));
    }
    $rootULElement = $domDocument->querySelector('ul');
    if ($rootULElement) {
        $itemsHtml = $rootULElement->outerHTML;
    }
} else {
    if ($pages === null || $pages->count() === 0) {
        $itemsHtml = '';
    } else {
        $itemsHtml = $buildTree($pages, $source === 'allPages' || $source === 'pageAllChildren');
    }
}

$content = '';
if (isset($itemsHtml[0])) {
    $content = '<component src="navigation-menu"' . $attributes . '>' . $itemsHtml . '</component>';
}
echo '<html>';

echo '<head>';
echo '<style>';
echo '.bearcms-navigation-element-item{word-wrap: break-word;}';
echo '</style>';
echo '</head>';

echo '<body>';
echo $content;
echo '</body>';

echo '</html>';
