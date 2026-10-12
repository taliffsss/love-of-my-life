<?php

declare(strict_types=1);

return [
    'name' => 'Jinelin Basela',
    'first_name' => 'Jinelin',

    // Secret that unlocks every change made from the page (photos, song, message). Set UPLOAD_KEY in .env to change it;
    // when it is missing or empty the key is the default below (compared ignoring case, spaces and surrounding quotes).
    'upload_key' => env('UPLOAD_KEY') ?: 'jinelin basela',
    // The song until one is chosen from the page (then storage/app/song.json takes over). Any YouTube video id.
    'song' => ['id' => 'fu9yk7gCTbc', 'title' => 'Palagi', 'artist' => 'TJ Monterde'],

    // Shown when the wrong upload key is entered.
    'upload_hint' => 'Hint: your complete full name.',
    'max_upload_bytes' => 12_000_000,

    // The letter shown (and slowly scrolled) in the right-hand panel.
    'letter' => [
        'Hello, how are you? If you’re wondering how I’ve been, well, I still miss you a lot, especially when I’m alone. Sometimes, all those feelings come rushing back.',
        'My heart still wants to fight for you, but my mind tells me otherwise. It tells me that if I keep holding on to you, I’ll only end up hurting you over and over again. It’s hard when my heart and mind can’t agree.',
        'But deep down, I know my mind is right. I don’t know where I’m headed, and there’s a part of me that feels incomplete, like something is missing. I feel like I’m a teenager all over again, hahaha.',
        'I’ll just hold on to my faith. If we’re meant to be, maybe, just maybe, our paths will cross again. I keep praying that you’ll be the one I get to spend the rest of my life with. All I ask of God is another chance with you.',
        'And if I could live another life, I would still choose you, over and over again. I don’t want anything more or anyone else. Just you.',
        'I hope that when the time is finally right, it won’t be too late for us. You’re the dream I find so hard to reach.',
    ],
];
