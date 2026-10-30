<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The words of the landing pages of the occasions (/birthday, /wedding, /anniversary, /boyfriend-girlfriend), one page per occasion
 * of App\Services\Designs::OCCASIONS, so each can be found by what people search for. The templates, their plans and the prices
 * on each page are read from Designs and the plans; only the wording lives here. /invitations has its own view and controller.
 */
final class OccasionPages
{
    /**
     * slug => the occasion it belongs to (a key of Designs::OCCASIONS), its search title (65 characters at most) and description
     * (160 at most), the headline and the lead, the three steps, the questions, the template of the "See a sample" button, the
     * sample's alt text and the mascot's. Nothing here promises what the app does not do (no replies, no guest list, no prices).
     *
     * @var array<string,array<string,mixed>>
     */
    public const PAGES = [
        'birthday' => [
            'occasion' => 'birthday',
            'name' => 'Birthday',
            'title' => 'Online birthday surprise page with photos · {brand}',
            'description' => 'Make an online birthday surprise: a gift box, scratch card or storybook that opens on your photos and birthday wishes. Free to build and preview.',
            'kicker' => 'Birthdays for him, for her and for friends',
            'h1' => 'A birthday surprise they open online.',
            'lead' => 'Skip the plain text message. Build a surprise box that opens on your photos and your birthday wishes, then send the link. It opens in any browser, with nothing to install.',
            'sample' => ['gift', 'sunset'],
            'steps' => [
                ['Pick a birthday template', 'A surprise box, a scratch card, a photo booth, a star for every wish. Each one comes with birthday wording you can change.'],
                ['Add your photos and your wishes', 'Write your message, upload your favourite photos from your phone and paste a YouTube link to their song.'],
                ['Send the link on the day', 'Share it in a message or a group chat, or print the QR code on a card. You can edit it after you send; the link stays the same.'],
            ],
            'faq' => [
                ['Can I surprise someone who lives far away?', 'Yes. They open a link in their phone’s browser, wherever they are, so it works for a friend abroad or a family member overseas as well as for someone across town.'],
                ['Is it only for partners?', 'No. Write it for a boyfriend, a girlfriend, a parent, a friend or a child’s party. The template wording is a starting point you can change.'],
                ['Can I lock it until their birthday?', 'You choose when to send the link, so send it on the day. You can also set a question that only they can answer, and the page stays closed until they type it.'],
            ],
            'alt' => 'A birthday gift box opening on a phone to show photos and a message',
            'mascot_alt' => 'Lettie, a smiling cupcake with a lit candle',
        ],
        'wedding' => [
            'occasion' => 'wedding',
            'name' => 'Wedding',
            'title' => 'Wedding vows and love story online · {brand}',
            'description' => 'Tell your wedding story online: vows, a storybook of how it happened, or a gift for the new husband or wife, with your photos and song. Free to build.',
            'kicker' => 'For the day, the vows and the forever after',
            'h1' => 'Your wedding story, page by page.',
            'lead' => 'Turn your photos and your words into a little storybook of how it happened, your vows on a page of their own, or a gift for the new husband or wife. Share it with everyone who loves you.',
            'sample' => ['storybook', 'blush'],
            'steps' => [
                ['Pick a wedding template', 'A storybook, your vows in wedding script, a wedding album, a journey from hello to I do. Each one comes with wording you can change.'],
                ['Add your photos and your words', 'Write the story, upload your photos from your phone and paste a YouTube link to your song.'],
                ['Share it with the people you love', 'Send the link, or print the QR code on the table card or the thank-you note. It opens in any browser.'],
            ],
            'faq' => [
                ['Is this the wedding invitation?', 'This page is for the story and the vows. For the invitation itself (date, place, dress code) see the wedding invitation templates on the invitations page.'],
                ['Can I make it a gift for my husband or wife?', 'Yes. There is a wrapped-gift template for the new husband or wife, and you can lock any page with a question only they can answer.'],
                ['Can my guests add photos?', 'No. You add the photos and the words; guests open and read the page.'],
            ],
            'alt' => 'A wedding storybook open on a phone, one page of the couple’s story',
            'mascot_alt' => 'Lettie, a smiling wedding bell in a veil and flower crown',
            'see_also' => ['/invitations', 'Wedding invitations'],
        ],
        'anniversary' => [
            'occasion' => 'anniversary',
            'name' => 'Anniversary',
            'title' => 'Digital anniversary gift with photos and song · {brand}',
            'description' => 'Make a digital anniversary gift: light up a star for every memory, or open a storybook of your years, with your photos, your song and your words.',
            'kicker' => 'Another year of choosing each other',
            'h1' => 'An anniversary love letter, online.',
            'lead' => 'Put your years on one private page: a star for every memory, a storybook of your story, or a letter that opens with a question only they can answer. Your photos, your song and your words.',
            'sample' => ['stars', 'midnight'],
            'steps' => [
                ['Pick an anniversary template', 'Stars that each hold a memory, a storybook, a timeline of your years, a love letter beside your photos.'],
                ['Add your memories', 'Write the letter, upload the photos you love and paste a YouTube link to your song.'],
                ['Send it on your day', 'Share the link in a message, or print the QR code on a card or a gift tag. They open it in their browser.'],
            ],
            'faq' => [
                ['Can I add our song?', 'Yes. Paste a YouTube link and the song plays while the page is open.'],
                ['What if I want to change it after I send it?', 'Change the letter, the photos, the song or the template whenever you like. The link and the QR code stay the same.'],
                ['Is it private?', 'Only people with the link or the QR code can open it, and with a question set, only someone who knows the answer. Pages are hidden from search engines.'],
            ],
            'alt' => 'A sky of stars on a phone, each star holding an anniversary memory',
            'mascot_alt' => 'Lettie, a smiling champagne bottle in a bow tie',
        ],
        'boyfriend-girlfriend' => [
            'occasion' => 'partner',
            'name' => 'Boyfriend and girlfriend',
            'title' => 'Digital love letter for boyfriend or girlfriend · {brand}',
            'description' => 'Send your boyfriend or girlfriend a digital love letter, monthsary surprise or scratch card with your photos and song, locked with a question only they know.',
            'kicker' => 'For monthsaries, sweet surprises and just because',
            'h1' => 'A love letter for your boyfriend or girlfriend.',
            'lead' => 'A monthsary message, a surprise, or a scratch card that asks “Will you be mine?”. Put your photos, your song and a letter on one private page that only they can open.',
            'sample' => ['scratch', 'rose'],
            'steps' => [
                ['Pick a template', 'A secret letter, a scratch card, a little book about the two of you, a present for no reason except love.'],
                ['Write it and choose the question', 'Add your photos, your song and your letter. Set a question only they can answer: an endearment, your first date, an inside joke.'],
                ['Send the link or the QR code', 'Share it in a message, or print the QR code on a card or the gift tag. You can change everything later.'],
            ],
            'faq' => [
                ['What is a monthsary page?', 'A page you make for your monthsary: a letter, your photos and your song, opened with a question only your partner can answer.'],
                ['Can I surprise them with a scratch card?', 'Yes. The scratch-card template hides your message under foil that they scratch away.'],
                ['Is it free?', 'Building and previewing are free, and only you can see it. To send it, choose the plan that includes your template.'],
            ],
            'alt' => 'A gold scratch card being scratched on a phone to reveal a message',
            'mascot_alt' => 'Lettie, a smiling red plush heart in a pink hoodie',
        ],
    ];

    /** The slugs, for the route constraint and the sitemap. @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::PAGES);
    }
}
