<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride;

/**
 * @phpstan-require-extends \Octfx\ScDataDumper\DocumentTypes\RootDocument
 */
trait HasTagSearchTerms
{
    /**
     * @return list<array{positiveTags: list<string>, negativeTags: list<string>}>
     */
    public function getTagSearchTerms(string $path = 'matchConditions/DataSetMatchCondition_TagSearch/tagSearch/TagSearchTerm'): array
    {
        $results = [];
        $terms = $this->getAll($path);

        foreach ($terms as $term) {
            $positiveTags = [];
            $negativeTags = [];

            foreach ($term->getAll('positiveTags/Reference@value') as $val) {
                if (is_string($val) && $val !== '') {
                    $positiveTags[] = $val;
                }
            }

            foreach ($term->getAll('negativeTags/Reference@value') as $val) {
                if (is_string($val) && $val !== '') {
                    $negativeTags[] = $val;
                }
            }

            $results[] = [
                'positiveTags' => $positiveTags,
                'negativeTags' => $negativeTags,
            ];
        }

        return $results;
    }
}
