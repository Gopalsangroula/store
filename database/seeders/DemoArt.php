<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Storage;

/**
 * Draws simple product pictures for the demo stores from Bootstrap Icons (MIT licence),
 * tinted per colour variant, so the demo looks finished without using anyone's photos.
 */
class DemoArt
{
    public static function product(int $storeId, string $name, string $icon, string $color): string
    {
        $paths = self::iconPaths($icon);
        $light = self::mix($color, '#ffffff', 0.35);
        $shadow = self::isLight($color) ? '.18' : '.28';
        $id = substr(md5($name.$color), 0, 6);

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="600" height="600">
<defs>
<linearGradient id="g{$id}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{$light}"/><stop offset="1" stop-color="{$color}"/></linearGradient>
<filter id="s{$id}" x="-30%" y="-30%" width="160%" height="160%"><feDropShadow dx="0" dy="2.2" stdDeviation="2.2" flood-color="#000" flood-opacity="{$shadow}"/></filter>
</defs>
<ellipse cx="50" cy="87" rx="25" ry="3.6" fill="#000" opacity=".10"/>
<g transform="translate(19 14) scale(3.875)" fill="url(#g{$id})" filter="url(#s{$id})">{$paths}</g>
</svg>
SVG;

        $path = "stores/{$storeId}/products/".str($name)->slug().'.svg';
        Storage::disk('public')->put($path, $svg);

        return $path;
    }

    /** A QR-looking placeholder. Shops replace it with their real Fonepay / bank QR in settings. */
    public static function demoQr(int $storeId): string
    {
        mt_srand(2026);
        $cells = '';
        $n = 29;

        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                $finder = ($x < 8 && $y < 8) || ($x >= $n - 8 && $y < 8) || ($x < 8 && $y >= $n - 8);

                if (! $finder && mt_rand(0, 1)) {
                    $cells .= "<rect x='{$x}' y='{$y}' width='1' height='1'/>";
                }
            }
        }

        $finder = fn ($x, $y) => "<rect x='{$x}' y='{$y}' width='7' height='7'/><rect x='".($x + 1)."' y='".($y + 1)."' width='5' height='5' fill='#fff'/><rect x='".($x + 2)."' y='".($y + 2)."' width='3' height='3'/>";

        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='-2 -2 33 40' width='330' height='400'><rect x='-2' y='-2' width='33' height='40' fill='#fff'/><g fill='#111'>{$cells}".$finder(0, 0).$finder($n - 7, 0).$finder(0, $n - 7)."</g><text x='14.5' y='35' font-size='1.9' text-anchor='middle' font-family='Arial' fill='#b91c1c'>DEMO QR - replace in Settings</text></svg>";

        $path = "stores/{$storeId}/payments/demo-qr.svg";
        Storage::disk('public')->put($path, $svg);

        return $path;
    }

    private static function iconPaths(string $icon): string
    {
        $file = base_path("node_modules/bootstrap-icons/icons/{$icon}.svg");
        $svg = is_file($file) ? file_get_contents($file) : '<svg><rect x="2" y="2" width="12" height="12" rx="2"/></svg>';

        preg_match('/<svg[^>]*>(.*)<\/svg>/s', $svg, $match);

        return trim($match[1] ?? '');
    }

    private static function mix(string $a, string $b, float $t): string
    {
        [$r1, $g1, $b1] = sscanf($a, '#%02x%02x%02x');
        [$r2, $g2, $b2] = sscanf($b, '#%02x%02x%02x');

        return sprintf('#%02x%02x%02x', $r1 + ($r2 - $r1) * $t, $g1 + ($g2 - $g1) * $t, $b1 + ($b2 - $b1) * $t);
    }

    private static function isLight(string $hex): bool
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return ($r * 299 + $g * 587 + $b * 114) / 1000 > 180;
    }
}
