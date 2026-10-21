<?php

namespace App\Support;

use App\Models\Tree;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

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
}
