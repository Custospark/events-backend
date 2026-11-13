<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use RuntimeException;

class QrCodeService
{
    public function svg(string $content, int $size = 360, int $margin = 2): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, $margin),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($content);
    }

    public function png(string $content, int $size = 480, int $margin = 2): string
    {
        $matrix = Encoder::encode($content, ErrorCorrectionLevel::M())->getMatrix();
        $modules = $matrix->getWidth();
        $total = $modules + ($margin * 2);
        $scale = max(1, intdiv($size, $total));
        $pixels = $total * $scale;

        $image = imagecreatetruecolor($pixels, $pixels);

        if ($image === false) {
            throw new RuntimeException('Unable to create the QR image canvas.');
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 15, 23, 42);

        imagefilledrectangle($image, 0, 0, $pixels, $pixels, $white);

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) !== 1) {
                    continue;
                }

                $left = ($x + $margin) * $scale;
                $top = ($y + $margin) * $scale;
                imagefilledrectangle(
                    $image,
                    $left,
                    $top,
                    $left + $scale - 1,
                    $top + $scale - 1,
                    $black,
                );
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }
}
