<?php

namespace App\Support;

use App\Models\Tree;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TreeQrCode
{
    /**
     * Render a 200px SVG QR code that links to the tree's absolute QR route.
     */
    public static function svg(Tree $tree): string
    {
        $renderer = new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString(route('trees.qr', $tree));
    }

    /**
     * The QR codes of many trees, keyed by tree id. Each takes a few milliseconds to draw, so they are cached
     * under their URL (which changes only with the tree id or APP_URL) and read back in one cache call.
     *
     * @param  Collection<int, Tree>  $trees
     * @return array<int, string>
     */
    public static function svgs(Collection $trees): array
    {
        $keys = $trees->mapWithKeys(fn (Tree $tree): array => [$tree->id => 'tree-qr:'.sha1(route('trees.qr', $tree))]);
        $cached = Cache::many($keys->values()->all());
        $missing = [];

        $svgs = $trees->mapWithKeys(function (Tree $tree) use ($keys, $cached, &$missing): array {
            $key = $keys[$tree->id];
            $svg = $cached[$key] ?? null;

            if ($svg === null) {
                $svg = $missing[$key] = self::svg($tree);
            }

            return [$tree->id => $svg];
        })->all();

        if ($missing !== []) {
            Cache::putMany($missing, now()->addYear());
        }

        return $svgs;
    }
}
