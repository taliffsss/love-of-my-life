<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Starting points for a new page, grouped by occasion: ten per occasion. A template is not a new look (the looks are
 * App\Services\Designs); it is a head start: a ready-made letter, the question that opens the page, and a layout and
 * colour theme that suit the mood. Everything it fills in stays editable, and every template uses a layout and theme
 * that exist in Designs, so any of them also works with every plan's template list (a draft may try any template).
 *
 * Shape of a template: key (unique, also the form value), name, blurb, layout, theme, eyebrow (the small heading of the
 * question, at most 60 characters), question (at most 120) and letter (paragraphs).
 */
final class OccasionTemplates
{
    public const OCCASIONS = Designs::OCCASIONS;

    /** @return array<string,list<array{key:string,name:string,blurb:string,layout:string,theme:string,eyebrow:string,question:string,letter:list<string>}>> */
    public static function all(): array
    {
        return [
            'anniversary' => [
                self::t('anniversary-first-year', 'Our first year', 'One full lap around the sun, together.', 'timeline', 'rose', 'Year one', 'What did we eat on our first date?', [
                    'One whole year. I still remember how nervous I was at the start, and how quickly that turned into the feeling of coming home.',
                    'You turned ordinary days into the ones I want to keep. Every photo here is a small proof that we really did all of this.',
                    'Happy anniversary, my love. Here is to year two, and every year we get after that.',
                ]),
                self::t('anniversary-love-letter', 'A love letter', 'Classic, quiet and from the heart.', 'classic', 'blush', 'Happy anniversary', 'What is the nickname only I call you?', [
                    'I do not say it enough, so I wrote it down: I am grateful for you. For your patience, your laugh and the way you make hard days lighter.',
                    'After all this time you still surprise me. I still look forward to coming home to you.',
                    'Thank you for loving me the way you do. I will keep loving you the same way, for as long as you will have me.',
                ]),
                self::t('anniversary-milestone', 'Years together', 'For a milestone you are proud of.', 'storybook', 'sunset', 'Our story so far', 'In which city did we first meet?', [
                    'Look how far we have come. The little apartments, the long drives, the plans that changed and the ones that came true.',
                    'We did not get here by luck. We got here by showing up for each other, again and again, when it was easy and when it was not.',
                    'I am proud of us. Happy anniversary, and thank you for every chapter.',
                ]),
                self::t('anniversary-under-stars', 'Under the stars', 'A sky of memories, one star each.', 'stars', 'midnight', 'Written in the stars', 'What song is ours?', [
                    'Out of everyone under this sky, I found you. I still cannot get over how lucky that is.',
                    'Each star here is a memory I would live again: a laugh, a trip, a quiet night. Tap them all and remember with me.',
                    'Happy anniversary. You are my favourite part of every night sky.',
                ]),
                self::t('anniversary-opened-letter', 'Sealed with a kiss', 'A letter to open slowly.', 'envelope', 'rose', 'Open when you are ready', 'What was the first thing I ever said to you?', [
                    'I wrote this the way people used to: by hand, with all my feelings, and sealed for you to open.',
                    'Another year with you has taught me that love is mostly small things done every day. Thank you for doing them with me.',
                    'Happy anniversary. I am yours, today and always.',
                ]),
                self::t('anniversary-polaroids', 'Our polaroids', 'Instant memories, framed.', 'polaroid', 'blush', 'Moments we kept', 'Where was our very first photo together taken?', [
                    'I keep every picture of us because every one of them is a good day I got to spend with you.',
                    'Flip through them slowly. Look how we smile in all of them, even when we say we hate photos.',
                    'Happy anniversary. Let us keep making more.',
                ]),
                self::t('anniversary-surprise', 'A little surprise', 'Scratch to reveal what is underneath.', 'scratch', 'sunset', 'Surprise for you', 'What is my favourite thing about you?', [
                    'You thought I forgot, did you? I never forget the day my life got better.',
                    'Underneath the foil is a memory I treasure and the words I wanted to say. Scratch away and read on.',
                    'Happy anniversary. You are my favourite surprise of all.',
                ]),
                self::t('anniversary-then-and-now', 'Then and now', 'Match up the moments of your story.', 'memory', 'rose', 'Do you remember?', 'What was the name of the place we first held hands?', [
                    'Do you remember? I do, every bit of it, and I hope these pictures bring it all back for you too.',
                    'Match the pairs and the letter opens, just like how every piece of our story clicked into place.',
                    'Happy anniversary. Same love, now with more memories.',
                ]),
                self::t('anniversary-cinema', 'Our movie', 'Full-screen, like a film of us.', 'cinematic', 'midnight', 'Starring us', 'What movie did we first watch together?', [
                    'If our life together were a film, this would be the part where the music swells and the credits are nowhere in sight.',
                    'No script, no retakes, just you and me finding the next scene together.',
                    'Happy anniversary to my favourite co-star and the leading role in my heart.',
                ]),
                self::t('anniversary-gift', 'Anniversary gift', 'A wrapped present of photos and words.', 'gift', 'blush', 'A gift for you', 'What was the first gift I ever gave you?', [
                    'I looked for the perfect gift and realised the best thing I have is our story, so I wrapped that up for you.',
                    'Open it, and let the photos rise out. Each one is a day I would give anything to have again.',
                    'Happy anniversary. You are the gift I am most thankful for.',
                ]),
            ],
            'wedding' => [
                self::t('wedding-vows', 'Our vows', 'Words to say yes to, again.', 'classic', 'blush', 'My vows to you', 'What is the date we promised forever?', [
                    'Today I choose you, in front of everyone who loves us, and I will choose you again on every ordinary day that follows.',
                    'I promise to listen, to laugh with you, to hold your hand in the hard times and to celebrate the good ones twice as loud.',
                    'You are my best friend and my person. I am so proud to call you my husband, my wife, my home.',
                ]),
                self::t('wedding-invitation', 'The invitation', 'A sealed envelope for the big day.', 'envelope', 'blush', 'You are invited', 'What is the first name of the couple?', [
                    'Together with our families, we invite you to celebrate the day we begin our life as one.',
                    'Your love and support have carried us here, and we cannot imagine this day without you.',
                    'Open the photos, save the date, and come celebrate with us. We cannot wait to see you.',
                ]),
                self::t('wedding-love-story', 'How we met', 'The love story, page by page.', 'storybook', 'rose', 'Once upon a time', 'Where did our story begin?', [
                    'Every love story has a beginning. Ours started with a chance hello that neither of us expected to change everything.',
                    'Through the years, the adventures and the long talks, we found out that home is a person.',
                    'And now, the next chapter begins. Thank you for being part of our story.',
                ]),
                self::t('wedding-thank-you', 'Thank you, family and friends', 'A grateful letter after the celebration.', 'polaroid', 'blush', 'With all our thanks', 'Which table were you sitting at?', [
                    'Thank you for celebrating with us. Seeing everyone we love in one place was the happiest feeling we have ever known.',
                    'Every hug, every toast and every dance made our day more than we dreamed it could be.',
                    'These are some of our favourite moments. We hope they make you smile as much as they make us.',
                ]),
                self::t('wedding-to-my-bride', 'To my bride', 'A letter for the morning of.', 'cinematic', 'sunset', 'Before I see you', 'What colour is your dress?', [
                    'In a little while I will see you walking toward me, and I will probably forget every word I planned to say.',
                    'So I wrote them down. I love you. I am certain of you. There is nobody else I want beside me for the rest of my life.',
                    'See you at the end of the aisle, my love.',
                ]),
                self::t('wedding-to-my-groom', 'To my groom', 'A note before the walk down the aisle.', 'scratch', 'blush', 'For my groom', 'What did you say the day you proposed?', [
                    'Today I get to marry my best friend, and I could not be more sure.',
                    'You make me braver and softer all at once. You are the calm in every storm and the joy in every day.',
                    'I cannot wait to call you my husband. See you soon.',
                ]),
                self::t('wedding-timeline', 'From hello to I do', 'The whole journey, one chapter at a time.', 'timeline', 'rose', 'Our journey', 'When was our first kiss?', [
                    'From a first hello to a first date, a first trip, a first home and a first yes, here is how we got to I do.',
                    'Every step was small on its own and wonderful together.',
                    'Thank you for walking all of them with me. There are so many more still ahead.',
                ]),
                self::t('wedding-reception-game', 'Guest memory game', 'A fun game for the reception table.', 'memory', 'sunset', 'Find the pairs', 'Who caught the bouquet?', [
                    'Match the pairs of photos and see how many memories you can find from our journey to the altar.',
                    'It is a little game to play while we celebrate, and a little reminder of how special you are to us.',
                    'Thank you for being here. Now go dance.',
                ]),
                self::t('wedding-anniversary-gift', 'Wedding gift to my spouse', 'A present for the first night as one.', 'gift', 'rose', 'My wedding gift', 'What was the song of our first dance?', [
                    'I have a gift for you, and it is not something you can hold. It is every memory I have of us, wrapped up and kept safe.',
                    'Open it whenever you want to remember how this day felt.',
                    'I love you, my partner for life. Welcome to forever.',
                ]),
                self::t('wedding-stars', 'Written in the stars', 'A sky full of wedding memories.', 'stars', 'midnight', 'Meant to be', 'In which month did we get engaged?', [
                    'Somewhere out of all the possible paths in the world, yours and mine crossed. I call that fate.',
                    'Tap every star for a moment we will never forget, from the proposal to the vows and the first dance.',
                    'Today the stars aligned. Here is to a lifetime of them.',
                ]),
            ],
            'partner' => [
                self::t('partner-monthsary', 'Happy monthsary', 'A sweet note for the month.', 'classic', 'rose', 'Happy monthsary', 'What is our monthsary date?', [
                    'Another month with you and I am still smiling at my phone like it is the first day.',
                    'Thank you for the little things: the good mornings, the random memes, the way you remember my order.',
                    'Happy monthsary, love. I like us a lot.',
                ]),
                self::t('partner-i-miss-you', 'I miss you', 'For when you are apart.', 'cinematic', 'midnight', 'Thinking of you', 'What do I always call you?', [
                    'Distance is hard, but it makes one thing clear: I would rather be bored with you than excited with anyone else.',
                    'Every photo here is a reminder that you are worth waiting for.',
                    'Count the days with me. I will see you soon.',
                ]),
                self::t('partner-sorry', 'I am sorry', 'A gentle way to say it properly.', 'envelope', 'blush', 'From the bottom of my heart', 'What do you want to hear from me?', [
                    'I am sorry. Not the quick kind of sorry, but the kind that comes from really thinking about how I made you feel.',
                    'You deserve someone who listens, and I want to be better at that. I am working on it, for you and for us.',
                    'Thank you for giving me the chance to make it right. I love you.',
                ]),
                self::t('partner-will-you-be-mine', 'Will you be mine?', 'A scratch-off ask.', 'scratch', 'sunset', 'I have a question', 'Do you know what I want to ask?', [
                    'I have been trying to find the right time to say this, so I made you a card.',
                    'You make me laugh, you make me feel safe and you make every day better. I do not want to be anyone else\'s.',
                    'So, will you be mine? Take your time, I will be right here, nervously waiting.',
                ]),
                self::t('partner-100-reasons', 'Reasons I love you', 'A list that does not run out.', 'storybook', 'rose', 'A few of my reasons', 'What is my favourite thing about you?', [
                    'I love how you laugh at your own jokes. I love how you hum when you think no one is listening.',
                    'I love that you are kind to strangers, loyal to your friends and endlessly patient with me.',
                    'There are far more than I can write here, but this is a start. I love you.',
                ]),
                self::t('partner-our-memories', 'Our memories', 'Match the moments we shared.', 'memory', 'blush', 'Remember this?', 'Where did we go on our first date?', [
                    'I went through my camera roll and could not stop smiling. Look at all the days we have already had together.',
                    'Find the pairs and the letter will open, and so will a little bit of my heart.',
                    'Here is to making a thousand more.',
                ]),
                self::t('partner-polaroid-dates', 'Date night polaroids', 'Our best nights, framed.', 'polaroid', 'sunset', 'Date night', 'What did we eat on our first date night?', [
                    'Some of my best nights were nothing special: a movie, a walk, sharing food. All of them were better because of you.',
                    'These are my favourites. I hope you have as much fun looking at them as I did picking them.',
                    'Let us book the next date. Your treat, maybe?',
                ]),
                self::t('partner-surprise-gift', 'A surprise for you', 'A gift to unwrap.', 'gift', 'rose', 'Just because', 'What is my favourite colour on you?', [
                    'I know it is not a holiday and it is not your birthday. I just wanted to remind you how much you mean to me.',
                    'Untie the ribbon and see what is inside: some of my favourite photos of us, and a few words I kept for you.',
                    'You deserve to be spoiled, and I plan to make a habit of it.',
                ]),
                self::t('partner-story', 'How we got together', 'Our story, a chapter per photo.', 'timeline', 'sunset', 'Our beginning', 'Who said I love you first?', [
                    'Remember when we were just talking, and I was too shy to admit I liked you? Look at us now.',
                    'Every photo is a chapter, from the first meet-up to the first trip to the first time I knew it was real.',
                    'I am so glad you were brave enough to take a chance on me.',
                ]),
                self::t('partner-forever-stars', 'You are my person', 'A heart of stars, just for them.', 'stars', 'midnight', 'My person', 'What is our song?', [
                    'In a world with so many people, I am glad it is you I get to text goodnight to.',
                    'Tap each star and see a piece of what makes us, us. It is not perfect, but it is ours.',
                    'I am yours, always. Goodnight, my love.',
                ]),
            ],
            'birthday' => [
                self::t('birthday-happy', 'Happy birthday', 'A warm, simple celebration.', 'classic', 'sunset', 'Happy birthday', 'How old are you turning?', [
                    'Happy birthday to someone who makes the whole world a little brighter just by being in it.',
                    'I hope today is filled with cake, laughter and every single thing you love.',
                    'Thank you for another year of you. I am so lucky to celebrate it with you.',
                ]),
                self::t('birthday-surprise-gift', 'Birthday gift', 'A box to open with photos inside.', 'gift', 'rose', 'A gift for your day', 'What did you wish for last year?', [
                    'I wrapped up something special for your birthday: some of my favourite photos of you and some words from the heart.',
                    'Open the box slowly. Good things are worth savouring, and so are you.',
                    'Happy birthday. I hope this year gives you everything you deserve.',
                ]),
                self::t('birthday-scratch', 'Scratch your surprise', 'Reveal your birthday wish.', 'scratch', 'sunset', 'Scratch to reveal', 'What is your favourite cake flavour?', [
                    'I could not wait to give you this one. Scratch away the foil to find your birthday surprise.',
                    'You are the kind of person who deserves surprises every day, and today I get to start.',
                    'Happy birthday, you. Make a wish and make it a big one.',
                ]),
                self::t('birthday-memories', 'Birthday memory game', 'Find the pairs from the year.', 'memory', 'blush', 'Match the memories', 'Where did we celebrate your last birthday?', [
                    'A lot happened this year, and I loved sharing it with you. Match the pairs to see some of my favourite moments.',
                    'When you find them all, the letter opens, just like another year opening up for you.',
                    'Happy birthday. Here is to making new memories to match.',
                ]),
                self::t('birthday-timeline', 'Another year of you', 'A year of moments, in order.', 'timeline', 'rose', 'The year in moments', 'Which month was your favourite this year?', [
                    'Scroll down and see the year that was: the big days, the quiet ones and the ones that made us laugh until we cried.',
                    'You grew, you tried new things, and you kept being someone I admire.',
                    'Happy birthday. I cannot wait to see what you do this year.',
                ]),
                self::t('birthday-card', 'A birthday card', 'A sealed card to open on the day.', 'envelope', 'blush', 'Do not open until today', 'What is your middle name?', [
                    'This card has been waiting for the day, and now it is yours. Open it.',
                    'I wanted to tell you that you are loved, more than you know, by more people than you realise.',
                    'Happy birthday. Stay exactly as wonderful as you are.',
                ]),
                self::t('birthday-polaroid', 'Photo booth birthday', 'Polaroid snapshots of your best bits.', 'polaroid', 'sunset', 'Say cheese', 'Who took our favourite photo?', [
                    'Here are the pictures I love best, the candid ones where you do not know I am looking.',
                    'You are most yourself in them: laughing, a little silly, completely lovable.',
                    'Happy birthday. Let us take a hundred more.',
                ]),
                self::t('birthday-stars', 'Make a wish', 'A star for every wish.', 'stars', 'midnight', 'Make a wish', 'What do you wish for most this year?', [
                    'Blow out the candles, or in this case, tap the stars. Each one is a wish I am making for you.',
                    'May you be healthy, loved, brave and surrounded by the people who make you laugh the hardest.',
                    'Happy birthday. I will be cheering for every wish to come true.',
                ]),
                self::t('birthday-story', 'The story of you', 'A storybook for the birthday star.', 'storybook', 'rose', 'The story of you', 'What is the name of your first pet?', [
                    'Every person has a story, and yours is one I am so glad to be a part of.',
                    'Turn the pages and see what I love about the person you have become.',
                    'Happy birthday. May your next chapter be your best one yet.',
                ]),
                self::t('birthday-cinema', 'Birthday premiere', 'A full-screen tribute to you.', 'cinematic', 'sunset', 'Now showing: you', 'What is your favourite film?', [
                    'Tonight, the spotlight is all yours. Sit back and watch a few of the best scenes from your year.',
                    'You are the lead in every story I tell, and I am proud to be in the audience.',
                    'Happy birthday. Cue the confetti.',
                ]),
            ],
        ];
    }

    /** @return array<string,array{name:string,blurb:string}> */
    public static function occasions(): array
    {
        return self::OCCASIONS;
    }

    /** The template with this key, or null when there is none (an unknown key never creates a page with odd content). */
    public static function find(?string $key): ?array
    {
        if ($key === null || $key === '') {
            return null;
        }
        foreach (self::all() as $templates) {
            foreach ($templates as $template) {
                if ($template['key'] === $key) {
                    return $template;
                }
            }
        }

        return null;
    }

    /** @param list<string> $letter */
    private static function t(string $key, string $name, string $blurb, string $layout, string $theme, string $eyebrow, string $question, array $letter): array
    {
        return compact('key', 'name', 'blurb', 'layout', 'theme', 'eyebrow', 'question', 'letter');
    }
}
