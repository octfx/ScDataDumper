<?php

namespace Octfx\ScDataDumper\Tests\Formats;

use Attribute;
use DOMDocument;
use DOMElement;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Tests\Fixtures\TestableFormat;
use Octfx\ScDataDumper\Tests\Fixtures\TestRootDocument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * BaseFormatTest - Test class for BaseFormat functionality
 *
 * This test class provides setup methods to create DOMDocument and Element
 * objects from XML fixtures for testing BaseFormat and its subclasses.
 */
#[CoversClass(BaseFormat::class)]
class BaseFormatTest extends TestCase
{
    /**
     * @var DOMDocument|null The DOMDocument loaded from XML fixture
     */
    protected ?DOMDocument $domDocument = null;

    /**
     * @var DOMElement|null The root element from the XML fixture
     */
    protected ?DOMElement $rootElement = null;

    /**
     * @var DOMElement|null A child element for testing nested queries
     */
    protected ?DOMElement $localizationElement = null;

    /**
     * @var TestableFormat|null The TestableFormat instance for testing
     */
    protected ?TestableFormat $testableFormat = null;

    /**
     * Setup method - Creates DOMDocument and Element from XML fixtures
     *
     * This method is called before each test to:
     * 1. Load an XML fixture into a DOMDocument
     * 2. Extract root and child elements for testing
     * 3. Instantiate TestableFormat with fixture data
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Load XML fixture
        $xmlFile = __DIR__.'/../Fixtures/xml/scitemmanufacturer_sample.xml';
        $this->domDocument = new DOMDocument;
        $this->domDocument->load($xmlFile);

        // Get root element
        $this->rootElement = $this->domDocument->documentElement;

        // Get child element for testing nested queries
        $localizationList = $this->domDocument->getElementsByTagName('Localization');
        if ($localizationList->length > 0) {
            $this->localizationElement = $localizationList->item(0);
        }

        // Instantiate TestableFormat with root element
        $this->testableFormat = new TestableFormat($this->rootElement);
    }

    /**
     * Teardown method - Clean up test resources
     *
     * This method is called after each test to:
     * 1. Reset DOMDocument and Element references to null
     * 2. Reset TestableFormat instance to null
     */
    protected function tearDown(): void
    {
        $this->domDocument = null;
        $this->rootElement = null;
        $this->localizationElement = null;
        $this->testableFormat = null;

        parent::tearDown();
    }

    /**
     * Test simple child element retrieval with path notation
     */
    public function test_get_basic_path_queries(): void
    {
        // Test 1: Given TestableFormat with nested XML structure,
        // When calling get('Components/SAttachableComponentParams'),
        // Then returns Element object matching to child node

        // Use entityclassdefinition_sample.xml which has Components/EAPhaseActivePropComponentDef
        $entityXmlFile = __DIR__.'/../Fixtures/xml/entityclassdefinition_sample.xml';
        $entityDom = new DOMDocument;
        $entityDom->load($entityXmlFile);
        $entityRoot = $entityDom->documentElement;
        $entityFormat = new TestableFormat($entityRoot);

        $componentsElement = $entityFormat->get('Components/EAPhaseActivePropComponentDef');
        $this->assertInstanceOf(Element::class, $componentsElement, 'Should return Element for valid path');
        $this->assertEquals('EAPhaseActivePropComponentDef', $componentsElement->nodeName, 'Element nodeName should match expected value');

        // Test 2: Given TestableFormat with valid path,
        // When calling get() with that path,
        // Then returned Element's nodeName matches expected value

        // Use scitemmanufacturer_sample.xml which has Localization/displayFeatures
        $manufacturerXmlFile = __DIR__.'/../Fixtures/xml/scitemmanufacturer_sample.xml';
        $manufacturerDom = new DOMDocument;
        $manufacturerDom->load($manufacturerXmlFile);
        $manufacturerRoot = $manufacturerDom->documentElement;
        $manufacturerFormat = new TestableFormat($manufacturerRoot);

        $displayFeaturesElement = $manufacturerFormat->get('Localization/displayFeatures');
        $this->assertInstanceOf(Element::class, $displayFeaturesElement, 'Should return Element for Localization/displayFeatures path');
        $this->assertEquals('displayFeatures', $displayFeaturesElement->nodeName, 'Element nodeName should be displayFeatures');

        // Test 3: Given TestableFormat with non-existent path,
        // When calling get() with path that does not exist,
        // Then returns provided default value

        $nonExistentElement = $manufacturerFormat->get('NonExistent/Path', 'default_value');
        $this->assertEquals('default_value', $nonExistentElement, 'Should return default value for non-existent path');

        $nonExistentElement2 = $manufacturerFormat->get('Components/NonExistent', null);
        $this->assertNull($nonExistentElement2, 'Should return null for non-existent path with null default');

        // Test 4: Given TestableFormat with nested structure,
        // When calling get() with 2+ depth path,
        // Then returns correct Element from deeper nesting level

        // Test 3-level path: Localization/displayFeatures/locationAudioPlayTrigger
        $audioTriggerElement = $manufacturerFormat->get('Localization/displayFeatures/locationAudioPlayTrigger');
        $this->assertInstanceOf(Element::class, $audioTriggerElement, 'Should return Element for 3-level path');
        $this->assertEquals('locationAudioPlayTrigger', $audioTriggerElement->nodeName, 'Element nodeName should be locationAudioPlayTrigger');
        $this->assertEquals('GlobalResourceAudio', $audioTriggerElement->getAttribute('__polymorphicType'), 'Should have correct polymorphicType');
    }

    /**
     * Test attribute retrieval using @ syntax and numeric conversion
     */
    public function test_get_attribute_queries(): void
    {
        // Test 1: Given TestableFormat with element having attributes,
        // When calling get('@attributeName'), Then returns attribute value

        // Use scitemmanufacturer_sample.xml which has Code attribute
        $manufacturerXmlFile = __DIR__.'/../Fixtures/xml/scitemmanufacturer_sample.xml';
        $manufacturerDom = new DOMDocument;
        $manufacturerDom->load($manufacturerXmlFile);
        $manufacturerRoot = $manufacturerDom->documentElement;
        $manufacturerFormat = new TestableFormat($manufacturerRoot);

        $codeValue = $manufacturerFormat->get('@Code');
        $this->assertEquals('ACAS', $codeValue, 'Should return string attribute value for @Code');

        // Test 2: Given TestableFormat with numeric attribute value (e.g., '0'),
        // When calling get() for that attribute, Then returns float (0.0)

        // Use entityclassdefinition_sample.xml which has numeric attributes
        $entityXmlFile = __DIR__.'/../Fixtures/xml/entityclassdefinition_sample.xml';
        $entityDom = new DOMDocument;
        $entityDom->load($entityXmlFile);
        $entityRoot = $entityDom->documentElement;
        $entityFormat = new TestableFormat($entityRoot);

        $invisibleValue = $entityFormat->get('@Invisible');
        $this->assertIsFloat($invisibleValue, 'Should return float for numeric attribute value');
        $this->assertEquals(0.0, $invisibleValue, 'Should return 0.0 for "0" attribute value');

        // Test another numeric attribute
        $bboxValue = $entityFormat->get('@BBoxSelection');
        $this->assertIsFloat($bboxValue, 'Should return float for numeric attribute value');
        $this->assertEquals(0.0, $bboxValue, 'Should return 0.0 for "0" attribute value');

        // Test 3: Given TestableFormat with string attribute value,
        // When calling get() for that attribute, Then returns string value

        // Use scitemmanufacturer_sample.xml which has string attributes
        $typeValue = $manufacturerFormat->get('@__type');
        $this->assertIsString($typeValue, 'Should return string for non-numeric attribute value');
        $this->assertEquals('SCItemManufacturer', $typeValue, 'Should return correct string value');

        // Test string path attribute
        $pathValue = $manufacturerFormat->get('@__path');
        $this->assertIsString($pathValue, 'Should return string for path attribute');
        $this->assertEquals('libs/foundry/records/scitemmanufacturer/acas.xml', $pathValue, 'Should return correct path value');

        // Test 4: Given TestableFormat with non-existent attribute,
        // When calling get('@missingAttribute'), Then returns provided default value

        $missingValue = $manufacturerFormat->get('@NonExistentAttribute', 'default_value');
        $this->assertEquals('default_value', $missingValue, 'Should return default value for non-existent attribute');

        $missingValue2 = $entityFormat->get('@MissingAttribute', null);
        $this->assertNull($missingValue2, 'Should return null for non-existent attribute with null default');
    }

    /**
     * Test default parameter behavior for missing keys and attributes
     */
    public function test_get_default_values(): void
    {
        // Use scitemmanufacturer_sample.xml for testing
        $manufacturerXmlFile = __DIR__.'/../Fixtures/xml/scitemmanufacturer_sample.xml';
        $manufacturerDom = new DOMDocument;
        $manufacturerDom->load($manufacturerXmlFile);
        $manufacturerRoot = $manufacturerDom->documentElement;
        $manufacturerFormat = new TestableFormat($manufacturerRoot);

        // Test 1: Given TestableFormat with non-existent key,
        // When calling get() with default value,
        // Then returns provided default

        $nonExistentKeyResult = $manufacturerFormat->get('NonExistentKey', 'default_value');
        $this->assertEquals('default_value', $nonExistentKeyResult, 'Should return provided default for non-existent key');

        // Test 2: Given TestableFormat,
        // When calling get() with null default for missing key,
        // Then returns null

        $nonExistentKeyWithNull = $manufacturerFormat->get('AnotherNonExistentKey', null);
        $this->assertNull($nonExistentKeyWithNull, 'Should return null for missing key when default is null');

        // Test 3: Given TestableFormat,
        // When calling get() with string default 'fallback' for missing key,
        // Then returns 'fallback'

        $nonExistentKeyWithFallback = $manufacturerFormat->get('YetAnotherNonExistentKey', 'fallback');
        $this->assertEquals('fallback', $nonExistentKeyWithFallback, 'Should return string default "fallback" for missing key');

        // Test 4: Given TestableFormat with element query,
        // When calling get() with default for missing attribute,
        // Then returns default

        $missingAttributeDefault = $manufacturerFormat->get('Localization/@MissingAttribute', 'attribute_default');
        $this->assertEquals('attribute_default', $missingAttributeDefault, 'Should return default for missing attribute on element query');

        // Test with null default on missing attribute
        $missingAttributeNull = $manufacturerFormat->get('Localization/@AnotherMissingAttribute', null);
        $this->assertNull($missingAttributeNull, 'Should return null for missing attribute with null default');

        // Test with numeric default on missing key
        $numericDefault = $manufacturerFormat->get('NonExistentNumericKey', 42);
        $this->assertEquals(42, $numericDefault, 'Should return numeric default for missing key');

        // Test with array default on missing key
        $arrayDefault = $manufacturerFormat->get('NonExistentArrayKey', ['default', 'array']);
        $this->assertEquals(['default', 'array'], $arrayDefault, 'Should return array default for missing key');
    }

    /**
     * Test local=true parameter prepends ./ to single-segment keys
     */
    public function test_get_local_mode(): void
    {
        // Use scitemmanufacturer_sample.xml for testing
        $manufacturerXmlFile = __DIR__.'/../Fixtures/xml/scitemmanufacturer_sample.xml';
        $manufacturerDom = new DOMDocument;
        $manufacturerDom->load($manufacturerXmlFile);
        $manufacturerRoot = $manufacturerDom->documentElement;
        $manufacturerFormat = new TestableFormat($manufacturerRoot);

        // Test 1: Given TestableFormat instance at current node,
        // When calling get('Localization', null, true) with single-segment key,
        // Then returns child element of current node (same as get('./Localization'))

        $localizationLocal = $manufacturerFormat->get('Localization', null, true);
        $this->assertInstanceOf(Element::class, $localizationLocal, 'Should return Element for local=true with single-segment key');

        $localizationExplicit = $manufacturerFormat->get('./Localization');
        $this->assertInstanceOf(Element::class, $localizationExplicit, 'Should return Element for ./Localization path');

        $this->assertEquals($localizationExplicit->nodeName, $localizationLocal->nodeName, 'Local=true with single-segment key should match explicit ./ prefix');
        $this->assertEquals('Localization', $localizationLocal->nodeName, 'Element nodeName should be Localization');

        // Test 2: Given TestableFormat instance with path containing /,
        // When calling get() with local=true,
        // Then behavior is same as local=false (no additional prefix added)

        $pathWithSlashLocalFalse = $manufacturerFormat->get('Localization/displayFeatures', null, false);
        $this->assertInstanceOf(Element::class, $pathWithSlashLocalFalse, 'Should return Element for path with / and local=false');

        $pathWithSlashLocalTrue = $manufacturerFormat->get('Localization/displayFeatures', null, true);
        $this->assertInstanceOf(Element::class, $pathWithSlashLocalTrue, 'Should return Element for path with / and local=true');

        $this->assertEquals($pathWithSlashLocalFalse->nodeName, $pathWithSlashLocalTrue->nodeName, 'Path containing / should behave same for local=true and local=false');
        $this->assertEquals('displayFeatures', $pathWithSlashLocalTrue->nodeName, 'Element nodeName should be displayFeatures');

        // Test 3: Given TestableFormat instance with attribute query,
        // When calling get('@Code', null, true),
        // Then returns attribute value without / prefix

        $attributeLocalTrue = $manufacturerFormat->get('@Code', null, true);
        $this->assertEquals('ACAS', $attributeLocalTrue, 'Should return attribute value with local=true');

        $attributeLocalFalse = $manufacturerFormat->get('@Code', null, false);
        $this->assertEquals('ACAS', $attributeLocalFalse, 'Should return attribute value with local=false');

        $this->assertEquals($attributeLocalFalse, $attributeLocalTrue, 'Attribute query should behave same for local=true and local=false');

        // Test another attribute
        $typeAttribute = $manufacturerFormat->get('@__type', null, true);
        $this->assertEquals('SCItemManufacturer', $typeAttribute, 'Should return correct attribute value with local=true');

    }

    /**
     * Test creating a format from a child node and calling get() on it
     */
    public function test_creating_format_from_child_node(): void
    {
        // Use scitemmanufacturer_sample.xml for testing
        $manufacturerXmlFile = __DIR__.'/../Fixtures/xml/scitemmanufacturer_sample.xml';
        $manufacturerDom = new DOMDocument;
        $manufacturerDom->load($manufacturerXmlFile);
        $manufacturerRoot = $manufacturerDom->documentElement;
        $manufacturerFormat = new TestableFormat($manufacturerRoot);

        // Test: Given TestableFormat instance,
        // When creating new TestableFormat from child element (Localization),
        // Then the new format's item is non-null and get() works correctly

        $localizationElement = $manufacturerFormat->get('Localization', null, true);
        $this->assertInstanceOf(Element::class, $localizationElement, 'Should return Element for Localization child node');

        // Create new TestableFormat from the child element
        $localizationFormat = new TestableFormat($localizationElement);

        // Verify that the new format can retrieve child elements
        $displayFeaturesFromChild = $localizationFormat->get('displayFeatures', null, true);
        $this->assertInstanceOf(Element::class, $displayFeaturesFromChild, 'Should return Element for displayFeatures from child format');
        $this->assertEquals('displayFeatures', $displayFeaturesFromChild->nodeName, 'Element nodeName should be displayFeatures');
    }

    /**
     * Test $elementKey fallback when $key parameter is null
     */
    public function test_get_with_element_key(): void
    {
        // Test 1: Given TestableFormat with $elementKey set to 'Components/EAPhaseActivePropComponentDef',
        // When calling get() with null $key,
        // Then uses $elementKey and returns Element at that path

        $entityXmlFile = __DIR__.'/../Fixtures/xml/entityclassdefinition_sample.xml';
        $entityDom = new DOMDocument;
        $entityDom->load($entityXmlFile);
        $entityRoot = $entityDom->documentElement;

        // Create a custom TestableFormat with specific $elementKey
        $formatWithElementKey = new class($entityRoot) extends TestableFormat
        {
            protected ?string $elementKey = 'Components/EAPhaseActivePropComponentDef';
        };

        $elementByElementKey = $formatWithElementKey->get(null);
        $this->assertInstanceOf(Element::class, $elementByElementKey, 'Should return Element when using $elementKey with null $key');
        $this->assertEquals('EAPhaseActivePropComponentDef', $elementByElementKey->nodeName, 'Element nodeName should match $elementKey path');

        // Test 2: Given TestableFormat with valid $elementKey,
        // When calling get() and element exists at that path,
        // Then returns Element matching path

        $elementWithExplicitKey = $formatWithElementKey->get('Components/EAPhaseActivePropComponentDef');
        $this->assertInstanceOf(Element::class, $elementWithExplicitKey, 'Should return Element for explicit key path');
        $this->assertEquals('EAPhaseActivePropComponentDef', $elementWithExplicitKey->nodeName, 'Element nodeName should match expected value');

        // Verify both calls return the same element
        $this->assertEquals(
            $elementByElementKey->nodeName,
            $elementWithExplicitKey->nodeName,
            'Both null $key and explicit key should return same element'
        );

        // Test 3: Given TestableFormat with $elementKey set but both $key and $elementKey are null,
        // Then throws RuntimeException

        // Create a custom TestableFormat with null $elementKey
        $formatWithNullElementKey = new class($entityRoot) extends TestableFormat
        {
            protected ?string $elementKey = null;
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Either $key must be provided or $elementKey set');

        // This should throw RuntimeException because both $key and $elementKey are null
        $formatWithNullElementKey->get(null);
    }

    /**
     * Test RootDocument attribute delegation to documentElement
     */
    public function test_get_root_document_attribute_fix(): void
    {
        // Test 1: Given BaseFormat instance wrapping RootDocument,
        // When calling get('@attributeName') for attribute on documentElement,
        // Then returns attribute value from documentElement

        $xmlFile = __DIR__.'/../Fixtures/xml/scitemmanufacturer_sample.xml';
        $rootDocument = new TestRootDocument;
        $rootDocument->load($xmlFile);

        // Create TestableFormat with RootDocument instance
        $format = new TestableFormat($rootDocument);

        // Test attribute retrieval from documentElement
        $codeValue = $format->get('@Code');
        $this->assertEquals('ACAS', $codeValue, 'Should return Code attribute value from documentElement');

        $typeValue = $format->get('@__type');
        $this->assertEquals('SCItemManufacturer', $typeValue, 'Should return __type attribute value from documentElement');

        $pathValue = $format->get('@__path');
        $this->assertEquals('libs/foundry/records/scitemmanufacturer/acas.xml', $pathValue, 'Should return __path attribute value from documentElement');

        // Test 2: Given BaseFormat with RootDocument,
        // When querying attribute that exists on documentElement,
        // Then returned value matches expected attribute value

        // Verify __ref attribute
        $refValue = $format->get('@__ref');
        $this->assertEquals('cfc0122d-5275-415a-a656-3dcb3346feb5', $refValue, 'Should return correct __ref value');

        // Test 3: Given BaseFormat with RootDocument,
        // When querying non-existent attribute,
        // Then returns default value

        $nonExistentAttribute = $format->get('@NonExistentAttribute', 'default_value');
        $this->assertEquals('default_value', $nonExistentAttribute, 'Should return default value for non-existent attribute');

        // Test 4: Given BaseFormat with RootDocument,
        // When querying numeric attribute value,
        // Then returns numeric type (float or string based on attribute value)

        // Test with EntityClassDefinition which has numeric attributes
        $entityXmlFile = __DIR__.'/../Fixtures/xml/entityclassdefinition_sample.xml';
        $entityRootDocument = new TestRootDocument;
        $entityRootDocument->load($entityXmlFile);

        $entityFormat = new TestableFormat($entityRootDocument);

        // Test numeric attribute (0 should be converted to 0.0)
        $invisibleValue = $entityFormat->get('@Invisible');
        $this->assertIsFloat($invisibleValue, 'Should return float for numeric attribute "0"');
        $this->assertEquals(0.0, $invisibleValue, 'Should return 0.0 for "0" attribute value');
    }

    /**
     * Test RuntimeException when $elementKey is null in canTransform()
     */
    public function test_get_exception_both_params_null(): void
    {
        // Test 1: Given TestableFormat with $elementKey set to null,
        // When calling canTransform(),
        // Then throws RuntimeException with message 'Element key cannot be null'

        $xmlFile = __DIR__.'/../Fixtures/xml/scitemmanufacturer_sample.xml';
        $dom = new DOMDocument;
        $dom->load($xmlFile);
        $rootElement = $dom->documentElement;

        // Create a custom TestableFormat with null $elementKey
        $formatWithNullElementKey = new class($rootElement) extends TestableFormat
        {
            protected ?string $elementKey = null;
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Element key cannot be null');

        // This should throw RuntimeException because $elementKey is null when calling canTransform()
        $formatWithNullElementKey->canTransform();
    }

    /**
     * Test has() method with valid elements, missing elements, and invalid format
     */
    public function test_has_method(): void
    {
        // Test 1: Given TestableFormat with existing element 'Components/EAPhaseActivePropComponentDef',
        // When calling has('Components/EAPhaseActivePropComponentDef', 'EAPhaseActivePropComponentDef'),
        // Then returns true

        $entityXmlFile = __DIR__.'/../Fixtures/xml/entityclassdefinition_sample.xml';
        $entityDom = new DOMDocument;
        $entityDom->load($entityXmlFile);
        $entityRoot = $entityDom->documentElement;
        $entityFormat = new TestableFormat($entityRoot);

        $hasValidElement = $entityFormat->has('Components/EAPhaseActivePropComponentDef', 'EAPhaseActivePropComponentDef');
        $this->assertTrue($hasValidElement, 'Should return true for existing element with matching name');

        // Test 2: Given TestableFormat with non-existent path,
        // When calling has('nonexistent/path'),
        // Then returns false

        $hasNonExistentPath = $entityFormat->has('nonexistent/path');
        $this->assertFalse($hasNonExistentPath, 'Should return false for non-existent path');

        // Test with another non-existent path
        $hasAnotherNonExistent = $entityFormat->has('Components/NonExistentElement');
        $this->assertFalse($hasAnotherNonExistent, 'Should return false for non-existent element in existing path');

        // Test 3: Given TestableFormat with existing element but wrong name,
        // When calling has('Components/EAPhaseActivePropComponentDef', 'WrongName'),
        // Then returns false

        $hasWrongName = $entityFormat->has('Components/EAPhaseActivePropComponentDef', 'WrongName');
        $this->assertFalse($hasWrongName, 'Should return false for existing element with wrong name');

        // Test with another wrong name scenario
        $hasAnotherWrongName = $entityFormat->has('Components/EAPhaseActivePropComponentDef', 'EAPhaseActivePropComponentDefXYZ');
        $this->assertFalse($hasAnotherWrongName, 'Should return false for existing element with mismatched name');

        // Test 4a: Given TestableFormat with attribute lookup,
        // When calling has('@Invisible'),
        // Then returns true for existing attribute

        $hasAttribute = $entityFormat->has('@Invisible');
        $this->assertTrue($hasAttribute, 'Should return true for existing attribute query');

        // Test 4b: Given TestableFormat with missing attribute lookup,
        // When calling has('@MissingAttribute'),
        // Then returns false

        $hasMissingAttribute = $entityFormat->has('@MissingAttribute');
        $this->assertFalse($hasMissingAttribute, 'Should return false for missing attribute query');

        // Test 4c: Given TestableFormat with local path syntax,
        // When calling has('./Components'),
        // Then returns true for existing element

        $hasLocalPath = $entityFormat->has('./Components');
        $this->assertTrue($hasLocalPath, 'Should return true for existing element with ./ prefix');

        // Test 4: Given TestableFormat,
        // When calling has('invalid.format'),
        // Then throws RuntimeException with message about invalid format

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Element key contains invalid format');

        // This should throw RuntimeException because key contains '.'
        $entityFormat->has('invalid.format');
    }

    /**
     * Test canTransform() method with success, missing element, and null item cases
     */
    public function test_can_transform(): void
    {
        // Test 1: Given TestableFormat with valid item and $elementKey 'Components/EAPhaseActivePropComponentDef',
        // When calling canTransform() and element exists,
        // Then returns true

        $entityXmlFile = __DIR__.'/../Fixtures/xml/entityclassdefinition_sample.xml';
        $entityDom = new DOMDocument;
        $entityDom->load($entityXmlFile);
        $entityRoot = $entityDom->documentElement;

        // Create a custom TestableFormat with specific $elementKey
        $formatWithElementKey = new class($entityRoot) extends TestableFormat
        {
            protected ?string $elementKey = 'Components/EAPhaseActivePropComponentDef';
        };

        $canTransformWithElement = $formatWithElementKey->canTransform();
        $this->assertTrue($canTransformWithElement, 'Should return true when element exists at $elementKey path');

        $manufacturerXmlFile = __DIR__.'/../Fixtures/xml/scitemmanufacturer_sample.xml';
        $manufacturerDom = new DOMDocument;
        $manufacturerDom->load($manufacturerXmlFile);
        $manufacturerRoot = $manufacturerDom->documentElement;

        // Test 2: Given TestableFormat with valid item but element does not exist at $elementKey path,
        // When calling canTransform(),
        // Then returns false

        // Create a custom TestableFormat with non-existent path
        $formatWithNonExistentElement = new class($manufacturerRoot) extends TestableFormat
        {
            protected ?string $elementKey = 'NonExistent/Path';
        };

        $canTransformWithNonExistent = $formatWithNonExistentElement->canTransform();
        $this->assertFalse($canTransformWithNonExistent, 'Should return false when element does not exist at $elementKey path');

        // Test 3: Given TestableFormat with null item,
        // When calling canTransform(),
        // Then returns false

        $formatWithNullItem = new TestableFormat(null);
        $this->assertFalse($formatWithNullItem->canTransform(), 'Should return false when item is null');

        // Test 4: Given TestableFormat with existing parent but non-existent child,
        // When calling canTransform(),
        // Then returns false

        $formatWithNonExistentChild = new class($manufacturerRoot) extends TestableFormat
        {
            protected ?string $elementKey = 'Components/NonExistentElement';
        };

        $canTransformWithNonExistentChild = $formatWithNonExistentChild->canTransform();
        $this->assertFalse($canTransformWithNonExistentChild, 'Should return false for non-existent child element');
    }

    /**
     * Test constructor rejects unsupported input types
     */
    public function test_constructor_rejects_unsupported_type(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('must be of type Octfx\\ScDataDumper\\DocumentTypes\\RootDocument|Octfx\\ScDataDumper\\Definitions\\Element|DOMNode|null, string given');

        new TestableFormat('not a dom node');
    }

    /**
     * Test toPascalCase(), transformArrayKeysToPascalCase(), and removeNullValues() helper methods
     */
    public function test_helper_methods(): void
    {
        // ===== Test toPascalCase() =====

        // Test 1: Given input 'drive_speed' or 'driveSpeed',
        // When calling toPascalCase(),
        // Then returns 'DriveSpeed'

        $pascalCase1 = $this->testableFormat->testableToPascalCase('drive_speed');
        $this->assertEquals('DriveSpeed', $pascalCase1, 'Should convert snake_case to PascalCase');

        $pascalCase2 = $this->testableFormat->testableToPascalCase('driveSpeed');
        $this->assertEquals('DriveSpeed', $pascalCase2, 'Should convert camelCase to PascalCase');

        // Test 2: Given input 'Uuid', 'Scu', 'Ifcs', 'Emp',
        // When calling toPascalCase(),
        // Then returns 'UUID', 'SCU', 'IFCS', 'EMP' (acronym handling)

        $uuidResult = $this->testableFormat->testableToPascalCase('Uuid');
        $this->assertEquals('UUID', $uuidResult, 'Should convert Uuid to UUID acronym');

        $scuResult = $this->testableFormat->testableToPascalCase('Scu');
        $this->assertEquals('SCU', $scuResult, 'Should convert Scu to SCU acronym');

        $ifcsResult = $this->testableFormat->testableToPascalCase('Ifcs');
        $this->assertEquals('IFCS', $ifcsResult, 'Should convert Ifcs to IFCS acronym');

        $empResult = $this->testableFormat->testableToPascalCase('Emp');
        $this->assertEquals('EMP', $empResult, 'Should convert Emp to EMP acronym');

        $stdItemResult = $this->testableFormat->testableToPascalCase('StdItem');
        $this->assertEquals('stdItem', $stdItemResult, 'Should convert StdItem to stdItem acronym');

        $alreadyPascal = $this->testableFormat->testableToPascalCase('DriveSpeed');
        $this->assertEquals('DriveSpeed', $alreadyPascal, 'Should preserve already PascalCase values');

        // Test additional snake_case variations
        $kebabCase = $this->testableFormat->testableToPascalCase('drive-speed');
        $this->assertEquals('DriveSpeed', $kebabCase, 'Should convert kebab-case to PascalCase');

        $mixedUnderscore = $this->testableFormat->testableToPascalCase('some_long_name');
        $this->assertEquals('SomeLongName', $mixedUnderscore, 'Should convert multi-word snake_case to PascalCase');

        // ===== Test transformArrayKeysToPascalCase() =====

        // Test 3: Given array with snake_case keys,
        // When calling transformArrayKeysToPascalCase(),
        // Then all keys are PascalCase

        $snakeArray = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'age' => 30,
        ];

        $pascalArray = $this->testableFormat->testableTransformArrayKeysToPascalCase($snakeArray);
        $this->assertEquals('John', $pascalArray['FirstName'], 'Should convert first_name to FirstName');
        $this->assertEquals('Doe', $pascalArray['LastName'], 'Should convert last_name to LastName');
        $this->assertEquals(30, $pascalArray['Age'], 'Should convert age to Age');
        $this->assertArrayNotHasKey('first_name', $pascalArray, 'Should remove snake_case key first_name');
        $this->assertArrayNotHasKey('last_name', $pascalArray, 'Should remove snake_case key last_name');
        $this->assertArrayNotHasKey('age', $pascalArray, 'Should remove snake_case key age');

        // Test 4: Given nested array with mixed case keys,
        // When calling transformArrayKeysToPascalCase(),
        // Then all keys at all levels are PascalCase

        $nestedArray = [
            'user_data' => [
                'first_name' => 'Jane',
                'contact_info' => [
                    'email_address' => 'jane@example.com',
                    'phone_number' => '123-456-7890',
                ],
            ],
            'system_settings' => [
                'drive_speed' => 100,
                'Uuid' => 'some-uuid',
            ],
        ];

        $transformedNested = $this->testableFormat->testableTransformArrayKeysToPascalCase($nestedArray);

        $this->assertArrayHasKey('UserData', $transformedNested, 'Should have UserData key at top level');
        $this->assertArrayHasKey('FirstName', $transformedNested['UserData'], 'Should have FirstName in nested array');
        $this->assertEquals('Jane', $transformedNested['UserData']['FirstName'], 'Should preserve value for FirstName');
        $this->assertArrayHasKey('ContactInfo', $transformedNested['UserData'], 'Should have ContactInfo in nested array');
        $this->assertArrayHasKey('EmailAddress', $transformedNested['UserData']['ContactInfo'], 'Should have EmailAddress in deeply nested array');
        $this->assertEquals('jane@example.com', $transformedNested['UserData']['ContactInfo']['EmailAddress'], 'Should preserve email value');
        $this->assertArrayHasKey('SystemSettings', $transformedNested, 'Should have SystemSettings key');
        $this->assertArrayHasKey('DriveSpeed', $transformedNested['SystemSettings'], 'Should have DriveSpeed');
        $this->assertEquals(100, $transformedNested['SystemSettings']['DriveSpeed'], 'Should preserve speed value');
        $this->assertArrayHasKey('UUID', $transformedNested['SystemSettings'], 'Should convert Uuid to UUID acronym');
        $this->assertEquals('some-uuid', $transformedNested['SystemSettings']['UUID'], 'Should preserve UUID value');

        // Test with null input
        $nullInput = $this->testableFormat->testableTransformArrayKeysToPascalCase(null);
        $this->assertEquals([], $nullInput, 'Should return empty array for null input');

        // Test with BaseFormat input
        $formatInput = $this->testableFormat;
        $formatResult = $this->testableFormat->testableTransformArrayKeysToPascalCase($formatInput);
        $this->assertIsArray($formatResult, 'Should convert BaseFormat to array');
        $this->assertArrayHasKey('code', $formatResult, 'Should have code key from BaseFormat toArray()');
        $this->assertEquals('ACAS', $formatResult['code'], 'Should preserve code value from toArray()');

        // ===== Test removeNullValues() =====

        // Test 5: Given array with null values and empty arrays,
        // When calling removeNullValues(),
        // Then those keys are removed from result

        $arrayWithNulls = [
            'name' => 'Test',
            'description' => null,
            'tags' => [],
            'value' => 42,
            'metadata' => null,
            'empty_collection' => [],
        ];

        $cleanedArray = $this->testableFormat->testableRemoveNullValues($arrayWithNulls);

        $this->assertArrayHasKey('name', $cleanedArray, 'Should keep name key');
        $this->assertEquals('Test', $cleanedArray['name'], 'Should keep name value');

        $this->assertArrayHasKey('value', $cleanedArray, 'Should keep value key');
        $this->assertEquals(42, $cleanedArray['value'], 'Should keep value');

        $this->assertArrayNotHasKey('description', $cleanedArray, 'Should remove description key (null)');
        $this->assertArrayNotHasKey('tags', $cleanedArray, 'Should remove tags key (empty array)');
        $this->assertArrayNotHasKey('metadata', $cleanedArray, 'Should remove metadata key (null)');
        $this->assertArrayNotHasKey('empty_collection', $cleanedArray, 'Should remove empty_collection key (empty array)');

        // Test 6: Given array with nested structure containing nulls,
        // When calling removeNullValues(),
        // Then nested nulls are also removed

        $nestedWithNulls = [
            'user' => [
                'name' => 'Alice',
                'email' => null,
                'profile' => [
                    'bio' => 'A bio',
                    'avatar' => null,
                ],
                'settings' => [],
            ],
            'options' => [
                'theme' => 'dark',
                'notifications' => null,
            ],
            'empty_section' => [],
        ];

        $cleanedNested = $this->testableFormat->testableRemoveNullValues($nestedWithNulls);

        $this->assertArrayHasKey('user', $cleanedNested, 'Should keep user key');
        $this->assertArrayHasKey('name', $cleanedNested['user'], 'Should keep user.name');
        $this->assertEquals('Alice', $cleanedNested['user']['name'], 'Should keep user.name value');

        $this->assertArrayNotHasKey('email', $cleanedNested['user'], 'Should remove user.email (null)');

        $this->assertArrayHasKey('profile', $cleanedNested['user'], 'Should keep user.profile');
        $this->assertArrayHasKey('bio', $cleanedNested['user']['profile'], 'Should keep user.profile.bio');
        $this->assertEquals('A bio', $cleanedNested['user']['profile']['bio'], 'Should keep bio value');

        $this->assertArrayNotHasKey('avatar', $cleanedNested['user']['profile'], 'Should remove user.profile.avatar (null)');
        $this->assertArrayNotHasKey('settings', $cleanedNested['user'], 'Should remove user.settings (empty array)');

        $this->assertArrayHasKey('options', $cleanedNested, 'Should keep options');
        $this->assertArrayHasKey('theme', $cleanedNested['options'], 'Should keep options.theme');
        $this->assertEquals('dark', $cleanedNested['options']['theme'], 'Should keep theme value');
        $this->assertArrayNotHasKey('notifications', $cleanedNested['options'], 'Should remove options.notifications (null)');

        $this->assertArrayNotHasKey('empty_section', $cleanedNested, 'Should remove empty_section (empty array)');

        // Test that non-null empty strings are preserved
        $arrayWithEmptyString = [
            'name' => '',
            'null_value' => null,
            'number' => 0,
            'boolean' => false,
        ];

        $cleanedWithEmptyString = $this->testableFormat->testableRemoveNullValues($arrayWithEmptyString);
        $this->assertArrayHasKey('name', $cleanedWithEmptyString, 'Should keep empty string key');
        $this->assertEquals('', $cleanedWithEmptyString['name'], 'Should preserve empty string value');
        $this->assertArrayHasKey('number', $cleanedWithEmptyString, 'Should keep zero key');
        $this->assertEquals(0, $cleanedWithEmptyString['number'], 'Should preserve zero value');
        $this->assertArrayHasKey('boolean', $cleanedWithEmptyString, 'Should keep false key');
        $this->assertFalse($cleanedWithEmptyString['boolean'], 'Should preserve false value');
        $this->assertArrayNotHasKey('null_value', $cleanedWithEmptyString, 'Should remove null value');
    }

    /**
     * Test toJson() method with JSON_THROW_ON_ERROR flag
     */
    public function test_to_json(): void
    {
        // Test 1: Given TestableFormat instance with valid toArray() result,
        // When calling toJson(),
        // Then returns JSON string with pretty formatting

        $jsonOutput = $this->testableFormat->toJson();
        $this->assertIsString($jsonOutput, 'Should return JSON string');
        $this->assertNotEmpty($jsonOutput, 'Should return non-empty JSON string');

        // Verify it's valid JSON
        $decodedData = json_decode($jsonOutput, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decodedData, 'Should decode to array');

        // Verify pretty formatting (contains newlines and indentation)
        $this->assertStringContainsString("\n", $jsonOutput, 'Should contain newlines for pretty formatting');
        $this->assertStringContainsString('    ', $jsonOutput, 'Should contain spaces for indentation');

        // Test 2: Given JSON output from toJson(),
        // When decoding back to array,
        // Then structure matches original array from toArray()

        $originalArray = $this->testableFormat->toArray();
        $this->assertEquals($originalArray, $decodedData, 'Decoded JSON should match original array from toArray()');

        // Test 3: Given TestableFormat with data containing slashes,
        // When calling toJson(),
        // Then slashes are not escaped in output (JSON_UNESCAPED_SLASHES)

        // Create a custom TestableFormat with slash-containing data
        $formatWithSlashes = new class($this->rootElement) extends TestableFormat
        {
            public function toArray(): ?array
            {
                return [
                    'path' => 'libs/foundry/records/scitemmanufacturer/acas.xml',
                    'url' => 'https://example.com/api/v1/data',
                    'file' => 'C:/Users/test/file.json',
                ];
            }
        };

        $jsonWithSlashes = $formatWithSlashes->toJson();
        $this->assertStringNotContainsString('\\/', $jsonWithSlashes, 'Should not have escaped slashes (\\/)');
        $this->assertStringContainsString('libs/foundry/records/scitemmanufacturer/acas.xml', $jsonWithSlashes, 'Should preserve slashes in path');
        $this->assertStringContainsString('https://example.com/api/v1/data', $jsonWithSlashes, 'Should preserve slashes in URL');
        $this->assertStringContainsString('C:/Users/test/file.json', $jsonWithSlashes, 'Should preserve slashes in file path');

        // Verify the JSON with slashes is still valid
        $decodedSlashes = json_decode($jsonWithSlashes, true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals('libs/foundry/records/scitemmanufacturer/acas.xml', $decodedSlashes['path'], 'Path value should be correct');
        $this->assertEquals('https://example.com/api/v1/data', $decodedSlashes['url'], 'URL value should be correct');
        $this->assertEquals('C:/Users/test/file.json', $decodedSlashes['file'], 'File path value should be correct');

        // Test 4: Given TestableFormat with data that cannot be encoded (e.g., INF),
        // When calling toJson(),
        // Then throws JsonException

        // Create a custom TestableFormat with unencodable data
        $formatWithUnencodable = new class($this->rootElement) extends TestableFormat
        {
            public function toArray(): ?array
            {
                return [
                    'infinity' => INF,
                    'nan' => NAN,
                ];
            }
        };

        $this->expectException(\JsonException::class);
        $this->expectExceptionMessage('Inf and NaN cannot be JSON encoded');

        // This should throw JsonException because INF and NAN cannot be encoded
        $formatWithUnencodable->toJson();
    }
}
