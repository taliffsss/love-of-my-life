<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use App\Services\Designs;
use App\Services\OccasionTemplates;
use Naluz\Database\Seeder;

/**
 * A demo account with one draft page for every occasion, made from that occasion's first starter template, so the
 * dashboard and the designs can be tried at once: demo@lovable.local / demo1234. Local use only (DatabaseSeeder runs it
 * only outside production). Safe to run again: the account's password is reset, its pages are made once.
 */
final class DemoSeeder extends Seeder
{
    public const EMAIL = 'demo@lovable.local';
    public const PASSWORD = 'demo1234';

    public function run(): void
    {
        $demo = User::query()->where('email', self::EMAIL)->first();
        if ($demo === null) {
            $demo = new User();
            $demo->forceFill(['name' => 'Demo', 'email' => self::EMAIL, 'password' => self::PASSWORD, 'is_admin' => false])->save();
        } else {
            $demo->forceFill(['password' => self::PASSWORD])->save();
        }

        if (Page::query()->where('user_id', $demo->id)->count() > 0) {
            return;
        }
        foreach (OccasionTemplates::all() as $occasion => $templates) {
            $template = $templates[0];
            $page = new Page();
            $page->forceFill([
                'user_id' => $demo->id,
                'slug' => Page::newSlug(),
                'recipient' => 'Demo ' . Designs::OCCASIONS[$occasion]['name'],
                'letter' => $template['letter'],
                'gate_enabled' => false,
                'gate_eyebrow' => $template['eyebrow'],
                'gate_question' => $template['question'],
                'layout' => $template['layout'],
                'theme' => $template['theme'],
                'occasion' => $occasion,
                'plan' => 'free',
                'searchable' => false,
            ])->save();
        }
    }
}
