<?php
/*
 * Bear CMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) Amplilabs Ltd.
 * Free to use under the MIT license.
 */

use BearCMS\Internal;
use BearCMS\Internal\ElementsHelper;

$app = BearFramework\App::get();
$context = $app->contexts->get(__DIR__);

$lazyLimit = 70;
$contextData = ElementsHelper::getComponentContextData($component);
$editable = $component->editable === 'true';
$group = $component->group;

$containerData = ElementsHelper::getContainerData($component->id);

$elements = $containerData['elements'];
$hasLazyLoading = sizeof($elements) > $lazyLimit;

$columnID = (string) $component->getAttribute('bearcms-internal-attribute-columns-id');
$floatingBoxID = (string) $component->getAttribute('bearcms-internal-attribute-floatingbox-id');
$flexibleBoxID = (string) $component->getAttribute('bearcms-internal-attribute-flexiblebox-id');
$inContainer = $component->getAttribute('bearcms-internal-attribute-container') !== 'none';
$renderElementsContainer = $inContainer && !isset($columnID[0]) && !isset($floatingBoxID[0]) && !isset($flexibleBoxID[0]);

$outputType = (string) $component->getAttribute('output-type');
$outputType = isset($outputType[0]) ? $outputType : 'full-html';
if ($outputType !== 'full-html') {
    $editable = false;
}

$lazyLoadServerData = '';

if (!empty($elements)) {
    if (isset($columnID[0])) {
        $columnsElement = ElementsHelper::getStructuralElement($containerData, $columnID);
        $elements = $columnsElement !== null ? [$columnsElement] : [];
    } elseif (isset($floatingBoxID[0])) {
        $floatingBoxElement = ElementsHelper::getStructuralElement($containerData, $floatingBoxID);
        $elements = $floatingBoxElement !== null ? [$floatingBoxElement] : [];
    } elseif (isset($flexibleBoxID[0])) {
        $flexibleBoxElement = ElementsHelper::getStructuralElement($containerData, $flexibleBoxID);
        $elements = $flexibleBoxElement !== null ? [$flexibleBoxElement] : [];
    } else if ($hasLazyLoading) {
        $remainingLazyLoadElements = (string) $component->getAttribute('bearcms-internal-attribute-remaining-lazy-load-elements');
        if ($remainingLazyLoadElements === '') {
            $remainingLazyLoadElements = [];
            foreach ($elements as $elementContainerData) {
                $remainingLazyLoadElements[] = $elementContainerData['id'];
            }
        } else {
            $remainingLazyLoadElements = explode(',', $remainingLazyLoadElements);
        }
        $tempElements = [];
        foreach ($elements as $elementContainerData) {
            $remainingLazyLoadElementIndex = array_search($elementContainerData['id'], $remainingLazyLoadElements);
            if ($remainingLazyLoadElementIndex === false) {
                continue;
            }
            $tempElements[] = $elementContainerData;
            $elementsToLoad[] = $elementContainerData['id'];
            unset($remainingLazyLoadElements[$remainingLazyLoadElementIndex]);
            if (sizeof($tempElements) >= $lazyLimit) {
                break;
            }
        }
        $elements = $tempElements;
        unset($tempElements);
        if (!empty($remainingLazyLoadElements)) {
            $loadMoreComponent = clone ($component);
            $loadMoreComponent->setAttribute('bearcms-internal-attribute-remaining-lazy-load-elements', implode(',', $remainingLazyLoadElements));
            $loadMoreComponent->setAttribute('bearcms-internal-attribute-container', 'none');
            $lazyLoadServerData = \BearCMS\Internal\TempClientData::set(['componentHTML' => (string) $loadMoreComponent]);
            ElementsHelper::$lastLoadMoreServerData = $lazyLoadServerData;
        }
    }
}

$styles = '';

if ($renderElementsContainer) {
    $spacing = $component->spacing;
    $width = $component->width;
    $className = 'bre' . md5($spacing . '$' . $width);
    $attributes = '';
    if ($editable) {
        $htmlElementID = 'brela' . md5($component->id);
        ElementsHelper::$editorData[] = ['container', $component->id, $contextData, $group];
        $attributes .= ' id="' . $htmlElementID . '"';
    }

    $styles .= '.' . $className . '{width:' . $width . ';text-align:left;' . ($editable ? '--bearcms-elements-spacing:' . $spacing . ';' : '') . '}';
    $styles .= '.' . $className . '>div:not(:last-child){margin-bottom:' . ($editable ? 'var(--bearcms-elements-spacing)' : $spacing) . ';}';

    if ($outputType === 'full-html') {
        $attributes .= ' class="bearcms-elements ' . $className . (strlen($component->class) > 0 ? ' ' . $component->class : '') . '"';
    }

    if ($hasLazyLoading && isset($lazyLoadServerData[0])) {
        $attributes .= ' data-bearcms-elements-lazy-load="' . htmlentities($lazyLoadServerData) . '"';
    }
}
echo '<html>';

if ($outputType === 'full-html') {
    echo '<head>';
    if ($renderElementsContainer) {
        echo '<style>' . $styles . '</style>';
        if ($hasLazyLoading) {
            echo '<link rel="client-packages-prepare" name="-bearcms-elements-lazy-load">';
            echo '<script>clientPackages.get(\'-bearcms-elements-lazy-load\')</script>';
        }
    }
    echo '</head>';
}

echo '<body>';
if ($renderElementsContainer) {
    if ($editable) {
        echo '<div>';
    }
    echo '<div' . $attributes . '>';
}
if (!empty($elements)) {
    $childrenContextData = $contextData;
    $childrenContextData['width'] = '100%';
    $childrenContextData['inElementsContainer'] = '1';
    if ($editable) {
        $childrenContextData['spacing'] = 'var(--bearcms-elements-spacing)';
    }
    if (isset($columnID[0])) {
        echo ElementsHelper::renderColumns($elements[0], $editable, $childrenContextData, $inContainer, $outputType);
    } elseif (isset($floatingBoxID[0])) {
        echo ElementsHelper::renderFloatingBox($elements[0], $editable, $childrenContextData, $inContainer, $outputType);
    } elseif (isset($flexibleBoxID[0])) {
        echo ElementsHelper::renderFlexibleBox($elements[0], $editable, $childrenContextData, $inContainer, $outputType);
    } else {
        echo ElementsHelper::renderContainerElements($elements, $editable, $childrenContextData, $outputType);
    }
}
if ($renderElementsContainer) {
    echo '</div>';
    if ($editable) {
        echo '</div>';
    }
}
echo '</body></html>';
