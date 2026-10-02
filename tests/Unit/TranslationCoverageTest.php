<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Every member-facing string has a Bangla translation in lang/bn.json:
 * keys passed to t()/$t()/tc()/__(), data keys rendered with $t()
 * (title/label/text/description) and the runtime keys listed in
 * lang/dynamic-keys.json. Same scan as scripts/missing-translations.mjs.
 */
class TranslationCoverageTest extends TestCase
{
    private const CALL = '/(?:\$tc?|\btc?|__)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1(?:\s*,\s*([\'"])((?:\\\\.|(?!\3).)*)\3)?/u';

    private const PROPERTY = '/\b(?:title|label|text|description):\s*([\'"])((?:\\\\.|(?!\1).)+)\1/u';

    /**
     * @return array<string, string>
     */
    private function dictionary(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 2).'/lang/bn.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<string>
     */
    private function files(string $dir, string $pattern): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            if (preg_match($pattern, $path) === 1 && preg_match('#/(components/ui|actions|routes|wayfinder)/#', $path) !== 1) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * @return array<string, string> key => file it is used in
     */
    private function usedKeys(): array
    {
        $root = dirname(__DIR__, 2);
        $keys = [];

        $files = [
            ...$this->files($root.'/resources/js', '/\.(vue|ts)$/'),
            ...$this->files($root.'/app', '/\.php$/'),
            $root.'/bootstrap/app.php',
        ];

        foreach ($files as $file) {
            $text = (string) file_get_contents($file);
            $memberJs = str_contains($file, '/resources/js/') && ! str_contains($file, '/resources/js/admin/');

            preg_match_all(self::CALL, $text, $calls, PREG_SET_ORDER);

            foreach ($calls as $match) {
                $keys[stripcslashes($match[2])] = $file;

                if (str_contains($match[0], 'tc(') && isset($match[4]) && $match[4] !== '') {
                    $keys[stripcslashes($match[4])] = $file;
                }
            }

            if ($memberJs) {
                preg_match_all(self::PROPERTY, $text, $properties, PREG_SET_ORDER);

                foreach ($properties as $match) {
                    $keys[stripcslashes($match[2])] = $file;
                }
            }
        }

        $dynamic = json_decode((string) file_get_contents($root.'/lang/dynamic-keys.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach ($dynamic as $key) {
            $keys[$key] = 'lang/dynamic-keys.json';
        }

        return $keys;
    }

    public function test_every_member_facing_string_has_a_bangla_translation()
    {
        $bn = $this->dictionary();
        $used = $this->usedKeys();
        $this->assertGreaterThan(300, count($used), 'The key scan found too little — check the patterns.');
        $this->assertArrayHasKey('Showing :from–:to of :total products', $used);

        $missing = array_diff_key($used, $bn);

        $this->assertSame([], $missing, "Add these to lang/bn.json (node scripts/missing-translations.mjs lists them):\n".implode("\n", array_keys($missing)));
    }

    public function test_translations_keep_their_placeholders()
    {
        $broken = [];

        foreach ($this->dictionary() as $key => $value) {
            preg_match_all('/:([a-z]+)/', $key, $expected);
            preg_match_all('/:([a-z]+)/', $value, $actual);
            $want = array_unique($expected[1]);
            $have = array_unique($actual[1]);
            sort($want);
            sort($have);

            if ($want !== $have) {
                $broken[] = $key;
            }
        }

        $this->assertSame([], $broken, 'These Bangla strings lost or gained a :placeholder.');
    }

    public function test_bangla_strings_are_not_left_in_english()
    {
        $untranslated = array_keys(array_filter(
            $this->dictionary(),
            fn (string $value, string $key) => $value === $key && preg_match('/[a-z]{3,}/i', $key) === 1,
            ARRAY_FILTER_USE_BOTH,
        ));

        $this->assertSame([], $untranslated);
    }
}
