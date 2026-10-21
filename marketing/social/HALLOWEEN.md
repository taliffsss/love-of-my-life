# Halloween campaign (17 – 31 October)

The Halloween template is offered from **17 October** (two weeks before the day) to **31 October**
(`config/saas.php` → `seasons`). Run the campaign in that window and not outside it: the template is hidden from the
site the rest of the year, so an ad that sends people to it in November would promise something they cannot pick.

Everything below is built by `marketing/make.sh halloween` (see the README). Captions are edited **here**; then
`marketing/make.sh collect` copies them next to the pictures and videos in `out/social/halloween/`.

## Honesty rules (same as the rest of the kit)

- No testimonials, no "X couples already", no customer numbers: there are none yet.
- "Start free" means the letter, the song and the design are free to build and preview. **Photos come with a plan**, so
  never say "free photos" or "free to send". Prices only as on the plan cards (`config/saas.php`).
- The Halloween template is a **Sweetheart / Forever** template. Say "Halloween template", not "free Halloween".
- Don't say how to pay; send people to the site.

## Tracking

Every link in this file is a placeholder like `{link:post-1}`. `build_halloween.py` (and `make.sh collect`) replaces it with the
UTM-tagged link of that placement, defined in `utm.py`, so the admin dashboard (**Admin → Traffic**) shows which post, reel
or ad brought each visitor, sign-up and customer. The TikTok, Instagram and Facebook **bio** links are in `out/social/halloween/LINKS.md`.
How the tags work and how to name new ones: `marketing/UTM.md`.

## Ready-to-post pictures and videos

Each section: the files it uses (in backticks) and the caption to paste. `>` lines are the text.

### Post 1: `hw-hook.png`
> Make this Halloween unforgettable. 🎃💌
>
> Skip the "happy halloween" text. Build them a haunted door, put your photos, your song and everything you have been too shy to say behind it, and lock it with a question only they can answer.
>
> They knock three times. The door creaks open. Your letter is waiting inside. 🦇
>
> Start free (send with Sweetheart or Forever) → {link:post-1}
>
> Spooky na, sweet pa 🎃 Gawan mo siya ng haunted door na bubukas sa sulat mo.
> Tag the one you would haunt with love 👇
> #halloween #halloweenph #loveletter #LoveOfMyLife

### Post 2: `hw-door.png`
> Knock. Knock. Knock. 🚪🎃
>
> Three knocks and a haunted door creaks open on your photos, your song and your letter. A sweet little jump scare, made by you.
>
> Make theirs before the 31st → {link:post-2}
>
> Tatlong katok, bubukas ang pinto 👻 Sulat mo ang nasa loob.
> #halloween #trickortreat #halloweenph #LoveOfMyLife

### Post 3: `hw-lock.png`
> Spooky on the outside. Soft on the inside. 🕯️
>
> On a computer, the Halloween lock screen turns as you move the mouse. Behind it, a question only the two of you know: the costume, the first date, the nickname. Get it right and the page opens.
>
> Start free (send with Sweetheart or Forever) → {link:post-3}
>
> Kayo lang ang may alam ng sagot 🎃
> #halloween #loveletter #giftideasph #LoveOfMyLife

### Post 4: `hw-steps.png`
> Halloween in three steps. 🎃
> 1. Write the letter and pick your photos and song.
> 2. Choose the Halloween template: a haunted door, three knocks, a treat inside.
> 3. Send the link. They knock. The door opens. Boo. 👻
>
> Open until the 31st → {link:post-4}
>
> Tatlong hakbang lang, spooky-sweet na. 🦇
> #halloween #halloweenph #LoveOfMyLife

### Post 5: `hw-poll.png`
> Trick or treat? 🎃🍬
>
> Comment 1 for trick or 2 for treat, then tag the person you would send a haunted love letter to. 👇
>
> Trick o treat? I-comment ang 1 o 2 at i-tag ang taong gusto mong padalhan.
> #halloween #trickortreat #couplegoals #LoveOfMyLife

### Reel 1: `halloween-knock.mp4`
> Trick or treat? 🎃
>
> Knock three times and a haunted door creaks open on your photos and your letter. Make them a Halloween surprise they will want to open again and again.
>
> Start free (send with Sweetheart or Forever) → {link:reel-1}
>
> Tatlong katok, bubukas ang pinto 👻
> Tag who you would knock for 👇
> #halloween #halloweenph #trickortreat #loveletter #LoveOfMyLife

### Reel 2: `halloween-lock.mp4`
> Spooky and sweet. 🕯️
>
> Lock your letter behind a question only they know, then watch the pumpkin lock screen open for them. Only the right answer lets them in. Boo. 👻
>
> Start free (send with Sweetheart or Forever) → {link:reel-2}
>
> Kayo lang ang may alam ng sagot 🎃
> #halloween #halloweenph #loveletter #LoveOfMyLife

## Meta ads

Use the objective **Traffic** (landing page views) to `https://loveofmylife.love`. Run **17 – 31 October**. Put the
audience, budget and bid in Ads Manager; nothing about the audience is assumed here (the product has no customers or
analytics yet, so test two audiences for a week and keep the one that gets cheaper landing-page views).

### Ad carousel (5 square cards): `ad-hw-1.png` `ad-hw-2.png` `ad-hw-3.png` `ad-hw-4.png` `ad-hw-5.png`
> Primary text: Boo! A love letter with a twist. 🎃 Knock three times and a haunted door opens on your photos, your song and your letter. Start free: build and preview it. Send it before the 31st with Sweetheart or Forever.
>
> Headline (1): A love letter with a twist
> Headline (2): They knock three times
> Headline (3): The door creaks open
> Headline (4): Locked with a question
> Headline (5): Start free, send by the 31st
>
> Description: Halloween template with Sweetheart or Forever, 17 to 31 October
> Call to action: Learn more
> Website: {link:ad-carousel}

### Feed link ad (1.91:1): `ad-hw-feed.png`
> Primary text: Trick or treat? Send a haunted love letter. 🎃 Your photos, your song and your words behind a door that opens after three knocks. Start free.
>
> Headline: Send a haunted love letter
> Description: Halloween template with Sweetheart or Forever, 17 to 31 October
> Call to action: Learn more
> Website: {link:ad-feed}

### Story / Reel ad (9:16): `ad-hw-story.png` `halloween-knock.mp4`
> Primary text: Knock three times. 🎃 A haunted love letter, free to start.
>
> Headline: Knock three times
> Call to action: Learn more
> Website: {link:ad-story}

## TikTok / Reels photo carousel (post the five slides in order): `car-hw-1.png` `car-hw-2.png` `car-hw-3.png` `car-hw-4.png` `car-hw-5.png`
> A spooky-sweet love letter 🎃 Swipe: they knock three times, the door creaks open, your letter is inside. Start free → link in bio #halloween #halloweenph #loveletter #LoveOfMyLife

## Schedule (PHT; times are guesses to test, as in the content plan)

| Day | Post |
|---|---|
| 17 Oct | Post 1 (hook) and open the ad carousel |
| 18 Oct | Reel 1 (knock) |
| 20 Oct | Post 2 (door) |
| 22 Oct | Reel 2 (lock) and the TikTok carousel |
| 24 Oct | Post 3 (lock) |
| 26 Oct | Post 5 (poll), announce nothing: it is only a poll |
| 28 Oct | Post 4 (steps) |
| 30 Oct | Reel 1 again with a "last two days" line; story ad on |
| 31 Oct | A Story with Post 2, then switch the ads off at midnight |
