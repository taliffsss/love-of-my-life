# E-mail sequences for Love of My Life

Made with the `email-sequence` structure. **The app does not send marketing e-mail yet**, so this is copy, timing and flow you can run by hand, in a mailer
(Resend, Mailchimp, Brevo…) or after we build sending. **Voice:** Lettie: warm, short, never pushy; one idea and one button per e-mail. **Honest:** no
testimonials, no customer numbers, no discounts that do not exist, no "free" for paid things. Prices only as on the plan cards: Sweet ₱200/month, Sweetheart ₱350/month, Forever ₱4,999 once. **Links:** every link carries
UTM tags with `utm_medium=email` (`UTM.md`): `utm_source=lifecycle&utm_medium=email&utm_campaign=<sequence>&utm_content=<email>`.
**Before sending to anyone:** they must have agreed to receive e-mail; every e-mail needs an unsubscribe link and the sender's address (Data Privacy Act and good practice). Benchmarks below are general
rules of thumb, **not measured for this product**.

## Sequence A: Onboarding (new sign-up → first page)

**Trigger:** account created. **Goal:** they build a page and preview it. **Exit:** they publish a page (go to B), or after email 5. **Audience:** all new accounts; 5 e-mails over 12 days.

| # | Subject (options) | Purpose | Timing | Button | Condition |
|---|---|---|---|---|---|
| 1 | "Welcome! Let's make your first page 💌" / "Hi {first_name}, your page is waiting" | Welcome, one next step | Immediately | Open your dashboard | Everyone |
| 2 | "Your page is half-written" / "Add one photo and one line" | Nudge to finish the draft | Day 2 | Continue your page | Has a draft with no letter or no photos |
| 3 | "Which way should they open it?" / "Envelope, scratch card, stars…" | Show the templates, the question | Day 4 | Pick a template | Has not set a lock question or template |
| 4 | "Before you send it" / "3 things to check on your phone" | Preview and share, explain photos come with a plan | Day 7 | Preview your page | Has a page, not published |
| 5 | "Is anything in the way?" / "Can I help?" | Ask for the blocker | Day 12 | Reply to this e-mail | Not published, not unsubscribed |

### Email 1 (Day 0)
**Subject:** Welcome! Let's make your first page 💌 **Preview:** It takes about ten minutes.
> Hi {first_name},
> I'm Lettie, your little love letter. Welcome.
> A page is one letter for one person: your photos, your song and your words, opened with a question only they can answer.
> Start with one thing: who is it for? Write their name and pick an occasion. Everything else can wait.
> **[Open your dashboard]**
> Building and previewing are free. You only choose a plan when you are ready to send it.
> Lettie

### Email 2 (Day 2)
**Subject:** Add one photo and one line **Preview:** Your page for {recipient} is waiting.
> Hi {first_name}, your page for {recipient} is half-written. 🙂
> The hardest part is the first line. Try this: "The first time I noticed …". Then add a photo from the day you mean.
> **[Continue your page]**
> You can change everything later; the link stays the same.

### Email 3 (Day 4)
**Subject:** Which way should they open it? **Preview:** Envelope, scratch card, stars and more.
> There are many ways to open a page: break a seal, scratch a card, tap every star, knock on a haunted door (in season), turn the pages of a little book.
> Pick the one that sounds like {recipient}, then lock it with a question only they would know: a nickname, a first date, an inside joke.
> **[Pick a template]**

### Email 4 (Day 7)
**Subject:** Before you send it, check three things **Preview:** On your phone, not your computer.
> 1. **Open the preview on your phone.** That is where most people will see it.
> 2. **Test the question.** Would only they know the answer?
> 3. **Know what a plan adds.** Photos belong to your account: a plan adds the photos and puts the page online (Sweet ₱200 a month, Sweetheart ₱350 a month, Forever ₱4,999 once). You can see it all in your dashboard before you pay.
> **[Preview your page]**

### Email 5 (Day 12)
**Subject:** Is anything in the way? **Preview:** Reply and I'll read it.
> Hi {first_name}, I haven't seen your page go out yet. Is something in the way? Too many choices? Not sure what to write? Just reply with one line; a real person reads it.
> **[Reply to this e-mail]** (a mailto link, not a button to a page)

**Flow**
```
[Account created] --> E1 (Day 0)
                        |
                  has a draft? --No--> E3 (Day 4)  [skip E2]
                        |Yes
                        v
                      E2 (Day 2) --> E3 (Day 4) --> E4 (Day 7) --> E5 (Day 12) --> [EXIT: sequence complete]
Any time: published a page --> [EXIT: go to Sequence B]    unsubscribed or contacted support in the last 48 h --> suppress
```

## Sequence B: Upgrade (draft or photo limit → paid)

**Trigger:** a page is ready (letter and at least one photo) but not published, or the account hit its photo limit. **Goal:** choose a plan. **Exit:** a payment is made. 3 e-mails over 7 days.

| # | Subject | Purpose | Timing | Button |
|---|---|---|---|---|
| 1 | "{recipient}'s page is ready to send" | Celebrate, show what a plan adds | Day 0 (when ready) | Choose a plan |
| 2 | "Which plan fits?" | Plain comparison (photos, templates, how long online) | Day 3 | Compare plans |
| 3 | "Need more photos?" (only if at the limit) | Photo pack ₱100 for 12 more, kept for good | Day 0 of the limit | Add photos |
> **E1:** "Your page for {recipient} is ready. A plan puts it online and gives you the photos and templates it includes. Sweet is ₱200 a month; Sweetheart is ₱350 a month with every template; Forever is ₱4,999 once. Pay a month at a time or once, and cancel from your dashboard."
> **E2:** the three plans in three lines; one recommendation by need (≤ 12 photos and classic templates → Sweet; 13 to 40 photos or the game and seasonal templates → Sweetheart; online for good → Forever). No yearly discount is claimed because there is none.

## Sequence C: Seasonal reminder (existing accounts, Oct / Dec / Jan)

**Trigger:** a season opens (Halloween 17 Oct, Christmas 11 Dec, Valentine 31 Jan) for accounts with a draft or a past page. **Goal:** make a seasonal page. 2 e-mails: the day it opens, and 3 days before the day.
> **Subject:** The Halloween door is open 🎃 **Body:** "The Halloween template is here until the 31st: they knock three times and the door opens on your photos and letter. Build it free, send it with Sweetheart or Forever."
> **Subject:** Three days left for Halloween.
Suppress for anyone who unsubscribed; one reminder per season only.

## A/B tests
1. **A1 subject:** "Welcome! Let's make your first page 💌" vs "Hi {first_name}, your page is waiting". Split 50/50, measure opens (small numbers: read it as a hint, not proof).
2. **A2 button:** "Continue your page" vs "Add a photo". Measure clicks.
3. **B1 send time:** morning vs evening (Philippine time).

## Metrics (rules of thumb, not measured here)
Onboarding mails typically open well above newsletters; track **per e-mail** open, click and unsubscribe, and **per sequence** the share who publish a page and who pay.
Review weekly for the first month. The number that matters: *accounts that built a page after E2*, and *paid after B1*.

## Setup checklist (any mailer)
1. Consent captured at sign-up (a checkbox on the form, or a separate opt-in) and an unsubscribe link in every e-mail.
2. Create three automations (A, B, C) with the triggers above; add each e-mail with the delays.
3. Add the exit conditions as segments (published, paid).
4. Tag all links (`UTM.md`). Check them in *Admin → Traffic* (`utm_medium=email`).
5. Send yourself every e-mail on a phone first.
6. **To automate from the app**, ask for a build: a mailer integration, the consent field, and the triggers (draft ready, photo limit, paid).
