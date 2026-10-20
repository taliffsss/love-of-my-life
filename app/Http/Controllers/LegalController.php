<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Screen;
use Psr\Http\Message\ResponseInterface;

/** The Terms and Conditions and the Privacy Policy: public, indexable pages (the text is in resources/views/legal). */
final class LegalController
{
    public function terms(): ResponseInterface
    {
        return Screen::render('legal.terms', ['legal' => (array) config('saas.legal')]);
    }

    public function privacy(): ResponseInterface
    {
        return Screen::render('legal.privacy', ['legal' => (array) config('saas.legal')]);
    }
}
