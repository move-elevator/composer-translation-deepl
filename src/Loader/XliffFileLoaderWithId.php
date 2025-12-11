<?php

declare(strict_types=1);

/*
 * This file is part of the "composer-translation-deepl" Composer package.
 *
 * (c) 2025 Konrad Michalik <km@move-elevator.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MoveElevator\ComposerTranslationDeepl\Loader;

use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * XliffFileLoaderWithId.
 *
 * Extends the standard XliffFileLoader to preserve the original trans-unit IDs
 * as metadata, so they can be used when saving the catalogue.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
class XliffFileLoaderWithId extends XliffFileLoader
{
    public function load(mixed $resource, string $locale, string $domain = 'messages'): MessageCatalogue
    {
        $catalogue = parent::load($resource, $locale, $domain);

        // Parse the XLIFF file to extract original IDs
        $this->extractOriginalIds($resource, $catalogue, $domain);

        return $catalogue;
    }

    private function extractOriginalIds(string $resource, MessageCatalogue $catalogue, string $domain): void
    {
        $content = file_get_contents($resource);
        if (false === $content) {
            return;
        }

        $dom = new \DOMDocument();
        $dom->loadXML($content);

        $transUnits = $dom->getElementsByTagName('trans-unit');

        /** @var \DOMElement $transUnit */
        foreach ($transUnits as $transUnit) {

            $id = $transUnit->getAttribute('id');
            $resname = $transUnit->getAttribute('resname');

            // Determine the message key (same logic as Symfony's XliffFileLoader)
            $source = null;
            foreach ($transUnit->childNodes as $child) {
                if ($child instanceof \DOMElement && 'source' === $child->nodeName) {
                    $source = $child->textContent;
                    break;
                }
            }

            // The key is resname if present, otherwise source
            $key = '' !== $resname ? $resname : $source;

            if (null === $key || '' === $key) {
                continue;
            }

            // Store the original ID in metadata
            $metadata = $catalogue->getMetadata($key, $domain) ?? [];
            $metadata['id'] = $id;
            $catalogue->setMetadata($key, $metadata, $domain);
        }
    }
}
