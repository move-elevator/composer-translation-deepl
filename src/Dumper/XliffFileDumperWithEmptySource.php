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

namespace MoveElevator\ComposerTranslationDeepl\Dumper;

use DOMDocument;
use DOMElement;
use DOMException;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\MessageCatalogue;

use function assert;

/**
 * XliffFileDumperWithEmptySource.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
class XliffFileDumperWithEmptySource extends XliffFileDumper
{
    /**
     * @param array<string, mixed> $options
     *
     * @throws DOMException
     */
    public function formatCatalogue(MessageCatalogue $messages, string $domain, array $options = []): string
    {
        $domDocument = new DOMDocument('1.0', 'utf-8');
        $domDocument->formatOutput = true;

        $xliff = $domDocument->appendChild($domDocument->createElement('xliff'));
        assert($xliff instanceof DOMElement);
        $xliff->setAttribute('version', '1.2');
        $xliff->setAttribute('xmlns', 'urn:oasis:names:tc:xliff:document:1.2');

        $file = $xliff->appendChild($domDocument->createElement('file'));
        assert($file instanceof DOMElement);
        $file->setAttribute('source-language', $messages->getLocale());
        $file->setAttribute('target-language', $messages->getLocale());
        $file->setAttribute('datatype', 'plaintext');
        $file->setAttribute('original', 'file.ext');

        $body = $file->appendChild($domDocument->createElement('body'));
        assert($body instanceof DOMElement);

        foreach ($messages->all($domain) as $source => $target) {
            $transUnit = $body->appendChild($domDocument->createElement('trans-unit'));
            assert($transUnit instanceof DOMElement);

            // Use original ID from metadata if available, otherwise fall back to key
            $metadata = $messages->getMetadata($source, $domain) ?? [];
            $originalId = $metadata['id'] ?? $source;

            $transUnit->setAttribute('id', $originalId);
            $transUnit->setAttribute('resname', $source);

            // Create empty source tag
            $transUnit->appendChild($domDocument->createElement('source'));

            // Create target with actual translation
            if ('' !== $target) {
                $targetElement = $domDocument->createElement('target');
                $targetElement->appendChild($domDocument->createTextNode($target));
                $transUnit->appendChild($targetElement);
            } else {
                $transUnit->appendChild($domDocument->createElement('target'));
            }

            // Add metadata if available
            if ($metadata = $messages->getMetadata($source, $domain)) {
                if (isset($metadata['target-attributes'])) {
                    $lastChild = $transUnit->lastChild;
                    assert($lastChild instanceof DOMElement);
                    foreach ($metadata['target-attributes'] as $key => $value) {
                        $lastChild->setAttribute($key, $value);
                    }
                }

                if (isset($metadata['notes'])) {
                    foreach ($metadata['notes'] as $note) {
                        $noteElement = $domDocument->createElement('note', $note['content'] ?? '');
                        if (isset($note['from'])) {
                            $noteElement->setAttribute('from', $note['from']);
                        }

                        $transUnit->appendChild($noteElement);
                    }
                }
            }
        }

        $xml = $domDocument->saveXML();
        assert(false !== $xml);

        return $xml;
    }
}
