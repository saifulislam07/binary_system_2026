<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Each of the 12 numbered business rules in CLAUDE.md must keep at least one
 * test class tagged #[Group('rule-N')] — run one rule's tests with
 * `php artisan test --group=rule-6`.
 */
class BusinessRuleCoverageTest extends TestCase
{
    public function test_every_business_rule_has_tagged_feature_tests()
    {
        $tagged = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/Feature'));

        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
                preg_match_all("/#\\[Group\\('rule-(\\d+)'\\)\\]/", (string) file_get_contents($file->getPathname()), $matches);

                foreach ($matches[1] as $rule) {
                    $tagged[(int) $rule][] = $file->getFilename();
                }
            }
        }

        foreach (range(1, 12) as $rule) {
            $this->assertNotEmpty($tagged[$rule] ?? [], "Business rule #{$rule} has no test class tagged #[Group('rule-{$rule}')]");
        }

        $this->assertSame(range(1, 12), array_values(array_intersect(range(1, 12), array_keys($tagged))));
    }
}
