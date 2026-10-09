<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use PHPUnit\Framework\TestCase;

/** Every language pack has exactly the keys of the English one (no missing or stray text). */
class TranslationsTest extends TestCase
{
    protected static function flatten(array $a, $prefix = '')
    {
        $out = [];
        foreach ($a as $k => $v) {
            if (is_array($v)) {
                $out = array_merge($out, self::flatten($v, $prefix.$k.'.'));
            } else {
                $out[] = $prefix.$k;
            }
        }
        sort($out);

        return $out;
    }

    public function testLanguagePacksAreComplete()
    {
        $dir = __DIR__.'/../../Resources/lang';
        $locales = array_values(array_filter(scandir($dir), function ($d) use ($dir) {
            return $d[0] !== '.' && is_dir($dir.'/'.$d);
        }));
        $this->assertGreaterThanOrEqual(7, count($locales));
        foreach (['messages', 'compat'] as $file) {
            $reference = self::flatten(require $dir.'/en/'.$file.'.php');
            foreach ($locales as $locale) {
                $keys = self::flatten(require $dir.'/'.$locale.'/'.$file.'.php');
                $this->assertSame([], array_values(array_diff($reference, $keys)), "$locale/$file.php: missing keys");
                $this->assertSame([], array_values(array_diff($keys, $reference)), "$locale/$file.php: unknown keys");
            }
        }
    }

    public function testPlaceholdersAreKept()
    {
        $dir = __DIR__.'/../../Resources/lang';
        foreach (['messages', 'compat'] as $file) {
            $en = require $dir.'/en/'.$file.'.php';
            foreach (glob($dir.'/*/'.$file.'.php') as $path) {
                $other = require $path;
                array_walk_recursive($en, function ($text, $key) use ($other, $path) {
                    preg_match_all('/:[a-z_]+/', $text, $m);
                    $flat = json_encode($other, JSON_UNESCAPED_UNICODE);
                    foreach ($m[0] as $placeholder) {
                        $this->assertStringContainsString($placeholder, $flat, basename(dirname($path)).": $key uses $placeholder");
                    }
                });
            }
        }
    }
}
