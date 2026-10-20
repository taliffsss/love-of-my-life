<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use Naluz\Auth\Auth;
use Naluz\Database\Connection;
use Naluz\Http\Response;
use Psr\Http\Message\ResponseInterface;

/** The dashboard's first-visit tour (partials/tour): finishing or skipping it is remembered on the account, so it shows once. */
final class TourController
{
    public function complete(Auth $auth, Connection $db): ResponseInterface
    {
        $db->table('users')->where('id', '=', (int) $auth->id())->whereNull('tour_seen_at')->update(['tour_seen_at' => gmdate('Y-m-d H:i:s')]);

        return Response::json(['seen' => true]);
    }
}
