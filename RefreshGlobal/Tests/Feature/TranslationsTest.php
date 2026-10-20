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

    /**
     * Every text the module shows comes from its own language packs (refreshglobal::…). Refresh's and FreeScout's
     * translations do not cover the module's 7 languages (Refresh has fr and de only): texts borrowed from them showed
     * in English in Spanish, Italian, Dutch and Portuguese (1.16.2).
     */
    public function testEveryShownTextIsTheModulesOwn()
    {
        $root = realpath(__DIR__.'/../..');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $borrowed = [];
        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (!preg_match('/\.php$/', $path) || preg_match('#/(Tests|Resources/lang)/#', $path)) {
                continue;
            }
            preg_match_all("/(?:__|trans_choice|@lang)\\(\\s*'([^']+)'/", file_get_contents($path), $m);
            foreach ($m[1] as $key) {
                if (strpos($key, 'refreshglobal::') !== 0) {
                    $borrowed[] = substr($path, strlen($root) + 1).': '.$key;
                }
            }
        }
        $this->assertSame([], $borrowed);
    }
}
