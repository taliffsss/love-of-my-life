<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use Naluz\Auth\Auth;
use Naluz\Database\Connection;
use Naluz\Http\Request;
use Naluz\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** The first-visit tours (partials/tour, partials/tour-editor): finishing or skipping one is remembered on the account, so it shows once. */
final class TourController
{
    /** tour => the users column that remembers it */
    private const COLUMNS = ['dashboard' => 'tour_seen_at', 'editor' => 'editor_tour_seen_at'];

    public function complete(ServerRequestInterface $request, Auth $auth, Connection $db): ResponseInterface
    {
        $tour = (string) (Request::body($request)['tour'] ?? 'dashboard');
        $column = self::COLUMNS[$tour] ?? self::COLUMNS['dashboard'];
        $db->table('users')->where('id', '=', (int) $auth->id())->whereNull($column)->update([$column => gmdate('Y-m-d H:i:s')]);

        return Response::json(['seen' => true]);
    }
}
