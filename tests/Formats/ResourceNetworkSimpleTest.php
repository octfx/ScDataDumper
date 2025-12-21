<?php

namespace Tests\Formats;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\ScUnpacked\ResourceNetworkSimple;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResourceNetworkSimpleTest extends TestCase
{
    #[Test]
    public function it_exposes_power_ranges_from_item_resource_state(): void
    {
        $xml = <<<'XML'
<EntityClassDefinition.Test>
  <Components>
    <ItemResourceComponentParams>
      <states>
        <ItemResourceState name="Online">
          <powerRanges>
            <range start="0" modifier="1.0" registerRange="Low"/>
            <range start="10" modifier="1.5" registerRange="High"/>
          </powerRanges>
        </ItemResourceState>
      </states>
    </ItemResourceComponentParams>
  </Components>
</EntityClassDefinition.Test>
XML;

        $doc = new DOMDocument;
        $doc->loadXML($xml);

        $element = new Element($doc->documentElement);
        $format = new ResourceNetworkSimple($element);

        $result = $format->toArray();

        $this->assertNotNull($result);
        $this->assertArrayHasKey('States', $result);
        $this->assertCount(1, $result['States']);

        $state = $result['States'][0];
        $this->assertArrayHasKey('PowerRanges', $state);
        $this->assertCount(2, $state['PowerRanges']);

        $first = $state['PowerRanges'][0];
        $this->assertSame(0.0, $first['Start']);
        $this->assertSame(1.0, $first['Modifier']);
        $this->assertSame('Low', $first['RegisterRange']);
    }
}
