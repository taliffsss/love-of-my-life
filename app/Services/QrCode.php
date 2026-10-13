<?php

declare(strict_types=1);

namespace App\Services;

use chillerlan\QRCode\QRCode as Generator;
use chillerlan\QRCode\QROptions;

/** A printable QR code (SVG) for a page link: print it on a card, a frame or the gift tag. */
final class QrCode
{
    public static function svg(string $data): string
    {
        $options = new QROptions([
            'outputType' => 'svg',
            'outputBase64' => false,
            'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
            'drawLightModules' => false,
            'connectPaths' => true,
            'svgAddXmlHeader' => true,
        ]);
        return (new Generator($options))->render($data);
    }
}
