<?php

namespace Octfx\ScDataDumper\Tests\Fixtures;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

/**
 * TestRootDocument - A RootDocument subclass for testing that bypasses ElementLoader
 *
 * This class allows testing of RootDocument functionality without requiring
 * ServiceFactory initialization, which is needed for production ElementLoader.
 */
final class TestRootDocument extends RootDocument
{
    /**
     * Load XML file without triggering ElementLoader (which requires ServiceFactory)
     */
    public function load(string $filename, int $options = 0): bool
    {
        // Load the XML file using \DOMDocument::load() directly to bypass ElementLoader
        $success = \DOMDocument::load($filename, $options | LIBXML_NOCDATA | LIBXML_NOBLANKS | LIBXML_COMPACT);

        if (! $success) {
            throw new \RuntimeException('Failed to load document');
        }

        // Initialize XPath (needed for XmlAccess trait)
        $this->initXPath();

        return $success;
    }
}
