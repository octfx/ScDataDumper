<?php

namespace Octfx\ScDataDumper\Tests\Helper;

use DOMDocument;
use DOMXPath;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Helper\XmlAccess;
use Octfx\ScDataDumper\Tests\Fixtures\TestRootDocument;
use PHPUnit\Framework\TestCase;

class XmlAccessTraitTest extends TestCase
{
    protected DOMDocument $domDocument;

    protected DOMXPath $domXPath;

    protected XmlAccessTestClass $testInstance;

    protected function setUp(): void
    {
        // Create a test DOMDocument
        $this->domDocument = new DOMDocument;
        $this->domDocument->loadXML('<?xml version="1.0" encoding="UTF-8"?>
<root>
    <child>
        <grandchild>value</grandchild>
    </child>
    <level1>
        <level2>
            <level3>
                <level4>deep value</level4>
            </level3>
        </level2>
    </level1>
</root>');

        // Create XPath instance
        $this->domXPath = new DOMXPath($this->domDocument);

        // Create test instance using a class that uses the XmlAccess trait
        $this->testInstance = new XmlAccessTestClass($this->domDocument);
    }

    /**
     * Test XPath-based child queries on Element instances
     */
    public function test_get_with_path(): void
    {
        // Create an Element from the root node
        $rootElement = new Element($this->domDocument->documentElement);

        // Test 1: Get grandchild element using 2-level path
        $grandchild = $rootElement->get('child/grandchild');
        $this->assertInstanceOf(Element::class, $grandchild, 'Should return Element for valid child/grandchild path');
        $this->assertEquals('grandchild', $grandchild->nodeName, 'Should return grandchild element');

        // Test 2: Get element from 3+ level path
        $deepElement = $rootElement->get('level1/level2/level3/level4');
        $this->assertInstanceOf(Element::class, $deepElement, 'Should return Element for valid 4-level path');
        $this->assertEquals('level4', $deepElement->nodeName, 'Should return level4 element');

        // Test 3: Get non-existent path returns default value
        $default = 'default_value';
        $nonExistent = $rootElement->get('nonexistent/path', $default);
        $this->assertEquals($default, $nonExistent, 'Should return default value for non-existent path');
    }

    /**
     * Test attribute retrieval with @ prefix from Element instances
     */
    public function test_get_attribute(): void
    {
        // Create a DOMDocument with elements and attributes
        $this->domDocument = new DOMDocument;
        $this->domDocument->loadXML('<?xml version="1.0" encoding="UTF-8"?>
<root name="root_name" id="1">
    <child title="child_title">
        <grandchild value="123" type="text">
            <deep number="456.78" />
        </grandchild>
    </child>
</root>');

        // Create XPath instance
        $this->domXPath = new DOMXPath($this->domDocument);

        // Create test instance using a class that uses the XmlAccess trait
        $this->testInstance = new XmlAccessTestClass($this->domDocument);

        // Create an Element from the root node
        $rootElement = new Element($this->domDocument->documentElement);

        // Test 1: Direct attribute access returns string value
        $name = $rootElement->get('@name');
        $this->assertEquals('root_name', $name, 'Should return attribute value as string');

        // Test 2: Numeric attribute returns float
        $id = $rootElement->get('@id');
        $this->assertEquals(1.0, $id, 'Should return numeric attribute as float');
        $this->assertIsFloat($id, 'Numeric attribute should be float type');

        // Test 3: Path + attribute query returns attribute from that element
        $title = $rootElement->get('child@title');
        $this->assertEquals('child_title', $title, 'Should return attribute value from child element');

        // Test 4: Deep path + attribute query
        $value = $rootElement->get('child/grandchild@value');
        $this->assertEquals(123.0, $value, 'Should return numeric attribute from deep path as float');
        $this->assertIsFloat($value, 'Numeric attribute should be float type');

        // Test 5: Float attribute conversion
        $number = $rootElement->get('child/grandchild/deep@number');
        $this->assertEquals(456.78, $number, 'Should return float attribute as float type');
        $this->assertIsFloat($number, 'Float attribute should be float type');

        // Test 6: Non-existent attribute returns default value
        $default = 'default_value';
        $missing = $rootElement->get('@missing', $default);
        $this->assertEquals($default, $missing, 'Should return default value for non-existent attribute');

        // Test 7: Non-existent attribute on non-existent path returns default
        $missingPath = $rootElement->get('nonexistent/path@attr', $default);
        $this->assertEquals($default, $missingPath, 'Should return default value for non-existent path with attribute');
    }

    /**
     * Test splitXPathAttribute() method with various patterns including predicates and @ in brackets
     */
    public function test_split_x_path_attribute(): void
    {
        // Use reflection to access the private splitXPathAttribute method
        $reflection = new \ReflectionClass($this->testInstance);
        $method = $reflection->getMethod('splitXPathAttribute');

        // Test 1: Simple path with attribute
        $result = $method->invoke($this->testInstance, 'path@attr');
        $this->assertEquals(['path', 'attr'], $result, 'Should split simple path@attr correctly');

        // Test 2: Multi-level path with attribute
        $result = $method->invoke($this->testInstance, 'path/to/element@attr');
        $this->assertEquals(['path/to/element', 'attr'], $result, 'Should split multi-level path@attr correctly');

        // Test 3: Path with predicate (ignores @ inside brackets)
        $result = $method->invoke($this->testInstance, 'element[@condition]/child@attr');
        $this->assertEquals(['element[@condition]/child', 'attr'], $result, 'Should ignore @ inside brackets and split correctly');

        // Test 4: Path with no attribute
        $result = $method->invoke($this->testInstance, 'path');
        $this->assertEquals(['path', null], $result, 'Should return path with null attribute when no @ present');

        // Test 5: Attribute alone (empty path)
        $result = $method->invoke($this->testInstance, '@attr');
        $this->assertEquals(['', 'attr'], $result, 'Should return empty path and attribute when @attr alone');
    }

    /**
     * Test XmlAccess trait behavior on RootDocument instance
     * Verifies that RootDocument and Element instances return the same results for identical path queries
     */
    public function test_get_on_root_document(): void
    {
        // Load a sample XML document using TestRootDocument
        $rootDocument = new TestRootDocument;
        $rootDocument->load(__DIR__.'/../Fixtures/xml/entityclassdefinition_sample.xml');

        // Test 1: Verify getDomDocument() returns DOMDocument instance (using reflection for protected method)
        $reflection = new \ReflectionClass($rootDocument);
        $method = $reflection->getMethod('getDomDocument');
        $domDocument = $method->invoke($rootDocument);

        $this->assertInstanceOf(DOMDocument::class, $domDocument, 'getDomDocument() should return DOMDocument instance');
        $this->assertSame($rootDocument, $domDocument, 'RootDocument is a DOMDocument, so should return itself');

        // Test 2: Compare RootDocument and Element results for path queries
        // Create an Element from the root document's document element
        $element = new Element($rootDocument->documentElement);

        // Test 2a: Get child element using path - both should return the same Element
        $rootResult = $rootDocument->get('defaultEditorColor');
        $elementResult = $element->get('defaultEditorColor');

        $this->assertInstanceOf(Element::class, $rootResult, 'RootDocument::get() should return Element for valid path');
        $this->assertInstanceOf(Element::class, $elementResult, 'Element::get() should return Element for valid path');
        $this->assertEquals(
            $rootResult->nodeName,
            $elementResult->nodeName,
            'RootDocument and Element should return same element for identical path'
        );

        // Test 2b: Get deep child element - both should return the same Element
        $rootDeepResult = $rootDocument->get('Components/EAPhaseActivePropComponentDef/capturedNotification');
        $elementDeepResult = $element->get('Components/EAPhaseActivePropComponentDef/capturedNotification');

        $this->assertInstanceOf(Element::class, $rootDeepResult, 'RootDocument::get() should return Element for deep path');
        $this->assertInstanceOf(Element::class, $elementDeepResult, 'Element::get() should return Element for deep path');
        $this->assertEquals(
            $rootDeepResult->nodeName,
            $elementDeepResult->nodeName,
            'RootDocument and Element should return same element for deep path'
        );

        // Test 2c: Get attribute value - both should return same value
        $rootAttr = $rootDocument->get('@Category');
        $elementAttr = $element->get('@Category');

        $this->assertEquals('EA', $rootAttr, 'RootDocument::get() should return attribute value');
        $this->assertEquals('EA', $elementAttr, 'Element::get() should return attribute value');
        $this->assertEquals(
            $rootAttr,
            $elementAttr,
            'RootDocument and Element should return same attribute value'
        );

        // Test 2d: Get attribute from nested element - both should return same value
        $rootNestedAttr = $rootDocument->get('defaultEditorColor@r');
        $elementNestedAttr = $element->get('defaultEditorColor@r');

        $this->assertEquals(1.0, $rootNestedAttr, 'RootDocument::get() should return nested attribute value');
        $this->assertEquals(1.0, $elementNestedAttr, 'Element::get() should return nested attribute value');
        $this->assertEquals(
            $rootNestedAttr,
            $elementNestedAttr,
            'RootDocument and Element should return same nested attribute value'
        );

        // Test 3: Verify non-existent path returns default value for both
        $default = 'default_value';
        $rootMissing = $rootDocument->get('nonexistent/path', $default);
        $elementMissing = $element->get('nonexistent/path', $default);

        $this->assertEquals($default, $rootMissing, 'RootDocument::get() should return default for non-existent path');
        $this->assertEquals($default, $elementMissing, 'Element::get() should return default for non-existent path');
    }
}

/**
 * Simple class that uses XmlAccess trait for testing purposes
 */
class XmlAccessTestClass
{
    use XmlAccess;

    public function __construct(
        protected DOMDocument $domDocument
    ) {}

    protected function getDomDocument(): DOMDocument
    {
        return $this->domDocument;
    }
}
