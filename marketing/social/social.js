// Facebook and TikTok kit: profile pictures, cover photos, feed posts and TikTok slides, one per URL:
// social.html?card=<name>. render_social.py screenshots every entry of CARDS at its size. Phone screens come from the
// promo's stills (marketing/out/promo/shots/stills/): run marketing/promo/make_promo.sh first.
//
// Prices are written here as words on pictures, so render_social.py checks them against config/saas.php before it renders.
(() => {
  const STILLS = "/marketing/out/promo/shots/stills/";
  const URL_TEXT = "loveofmylife.love";
  const HEART = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-8-4.9-8-11.1C4 6.7 6.3 5 8.6 5c1.5 0 2.9.8 3.4 2 .5-1.2 1.9-2 3.4-2C17.7 5 20 6.7 20 9.9 20 16.1 12 21 12 21z"/></svg>';
  const RINGS = '<svg viewBox="0 0 64 40" width="64" height="40" fill="none" stroke="currentColor" stroke-width="3"><circle cx="22" cy="22" r="14"/><circle cx="42" cy="22" r="14"/><path d="M22 4l4 4-4 4-4-4z" fill="currentColor"/></svg>';
  const SPARK = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 1c.8 6.2 4.8 10.2 11 11-6.2.8-10.2 4.8-11 11-.8-6.2-4.8-10.2-11-11 6.2-.8 10.2-4.8 11-11z"/></svg>';
  const PLANE = '<svg viewBox="0 0 48 48" fill="currentColor"><path d="M44 6 4 21l13 5 5 13 5-9 10 7zM19 25l17-13-12 16z"/></svg>';
  const logo = (size = 42) => `<div class="logo" style="font-size:${size}px">${HEART}<span>Love of My Life</span></div>`;
  const phone = (key, w, style = "") => `<div class="phone" style="width:${w}px;height:${Math.round((w - 24) / 0.4621 + 24)}px;${style}"><div class="screen"><img src="${STILLS}${key}.jpg" alt="" /><span class="island"></span></div></div>`;
  const abs = (x, y, html, style = "") => `<div class="abs" style="left:${x}px;top:${y}px;${style}">${html}</div>`;
  const lettie = (x, y, size, pose) => abs(x, y, `<div class="lettie" data-pose="${pose}" style="width:${size}px;height:${size}px"></div>`);
  const K = (t, size = 28) => `<div class="kicker" style="font-size:${size}px">${t}</div>`;
  const H = (t, size, width, mt = 0) => `<div class="headline" style="font-size:${size}px;width:${width}px;margin-top:${mt}px">${t}</div>`;
  const S = (t, width, size = 34, mt = 0) => `<div class="sub" style="font-size:${size}px;width:${width}px;margin-top:${mt}px">${t}</div>`;
  const U = (size = 32, mt = 0) => `<div style="margin-top:${mt}px"><span class="url" style="font-size:${size}px">${URL_TEXT}</span></div>`;
  const GAP = (n) => `<div style="height:${n}px"></div>`;
  const SPACE = '<div class="grow"></div>';
  const col = (pad, html, justify = "flex-start") => `<div class="col" style="padding:${pad};justify-content:${justify}">${html}</div>`;
  const chips = (list) => `<div class="row" style="gap:14px;flex-wrap:wrap;width:940px">${list.map((t) => `<span class="pill">${t}</span>`).join("")}</div>`;

  function rand(seed) {
    let s = seed;
    return () => ((s = (s * 16807) % 2147483647) - 1) / 2147483646;
  }
  // soft scattered decoration behind the words
  function scatter(w, h, kind = "heart", n = 14, seed = 3) {
    const r = rand(seed);
    const items = Array.from({ length: n }, () => {
      const x = (r() * w) | 0, y = (r() * h) | 0, size = (16 + r() * 28) | 0;
      if (kind === "confetti") {
        const colours = ["#ff9ec1", "#ffd36e", "#8fd3ff", "#b8f0c4", "#d9b3ff"];
        return `<i style="position:absolute;left:${x}px;top:${y}px;width:${size / 2}px;height:${size}px;border-radius:${r() > 0.5 ? "50%" : "3px"};background:${colours[(r() * 5) | 0]};transform:rotate(${(r() * 180) | 0}deg);opacity:.75"></i>`;
      }
      const spooky = ["🎃", "🦇", "👻", "🕸️", "🕯️"];
      const glyph = kind === "halloween" ? spooky[(r() * spooky.length) | 0] : { heart: "♥", spark: "✦", ring: "◯" }[kind];
      return `<span style="left:${x}px;top:${y}px;font-size:${size + 8}px;${kind === "spark" ? "color:var(--gold);opacity:.4" : ""}">${glyph}</span>`;
    });
    return `<div class="hearts abs" style="left:0;top:0;width:${w}px;height:${h}px">${items.join("")}</div>`;
  }

  const OCC = {
    partner: { theme: "", deco: "heart", mark: HEART },
    anniversary: { theme: "t-gold", deco: "spark", mark: SPARK },
    wedding: { theme: "t-ivory", deco: "ring", mark: RINGS },
    birthday: { theme: "t-party", deco: "confetti", mark: SPARK },
    invitation: { theme: "t-navy", deco: "spark", mark: SPARK },
  };

  // ---- layouts ------------------------------------------------------------------------------------------------

  // A service post (Facebook feed 4:5): what it is, two phones, the designs it comes with, the address.
  function service({ occ, kicker, headline, sub, stills, designs }) {
    const o = OCC[occ];
    return {
      size: [1080, 1350], theme: o.theme,
      build: () => scatter(1080, 1350, o.deco, 12, 5) + col("78px 70px 60px",
        K(kicker) + H(headline, 100, 940, 18) + GAP(26) +
        `<div class="row" style="gap:0">${phone(stills[0], 250, "transform:rotate(-5deg) translateX(18px)")}${phone(stills[1], 250, "transform:rotate(5deg) translateX(-18px);margin-top:36px")}</div>` +
        GAP(26) + S(sub, 900, 32) + GAP(18) + chips(designs) + SPACE +
        `<div class="row" style="gap:28px">${logo(36)}<span class="url" style="font-size:26px;padding:12px 24px">${URL_TEXT}</span></div>`),
    };
  }

  // A big-words post with Lettie or an ornament (Facebook feed 1:1 or 4:5).
  function statement({ h = 1080, theme = "", kicker, headline, size = 112, sub, pose, deco = "heart", mark, tone }) {
    return {
      size: [1080, h], theme,
      build: () => scatter(1080, h, deco, 12, 9) + col("84px 80px 64px",
        K(kicker) + H(headline, size, 900, 20) + SPACE +
        (pose ? `<div class="lettie" data-pose="${pose}" style="width:${h > 1100 ? 360 : 280}px;height:${h > 1100 ? 360 : 280}px"></div>` : mark ? `<div class="orn" style="width:120px;color:var(--gold)">${mark}</div>` : "") +
        SPACE + S(sub, 860, 34) + GAP(26) + U(30) + GAP(10)),
    };
  }

  // Two phones to vote between (comments are the vote).
  function poll({ theme = "", kicker, headline, a, b, ask }) {
    return {
      size: [1080, 1350], theme,
      build: () => scatter(1080, 1350, "heart", 10, 11) + col("78px 70px 60px",
        K(kicker) + H(headline, 104, 920, 18) + GAP(30) +
        `<div class="row" style="gap:60px;align-items:flex-start">` +
        [a, b].map((x, i) => `<div style="display:flex;flex-direction:column;align-items:center;gap:18px">${phone(x.still, 330)}<div class="row" style="gap:14px"><span class="num" style="width:64px;height:64px;font-size:40px">${i + 1}</span><span class="hand" style="font-size:54px">${x.label}</span></div></div>`).join("") +
        `</div>` + SPACE + S(ask, 880, 38) + GAP(20) + logo(34)),
    };
  }

  // One plan, with its price, taken from config/saas.php (checked at render).
  function price({ theme = "", plan, line, price: amount, per, alt, ticks, note }) {
    return {
      size: [1080, 1350], theme,
      build: () => scatter(1080, 1350, "heart", 10, 13) + col("78px 80px 60px",
        K(plan) + H(line, 92, 900, 16) + GAP(30) +
        `<div class="panel" style="width:900px"><div class="price" style="font-size:150px;color:var(--gold)">${amount}</div>` +
        `<div class="sub" style="font-size:36px;margin-top:6px">${per}</div>` +
        (alt ? `<div class="sub" style="font-size:30px;margin-top:10px">${alt}</div>` : "") +
        `<div style="margin-top:34px;display:flex;flex-direction:column;gap:18px">${ticks.map((t) => `<div class="tick">${t}</div>`).join("")}</div></div>` +
        SPACE + S(note, 860, 30) + GAP(22) + U(30) + GAP(8)),
    };
  }

  function steps({ kicker, headline, list, theme = "" }) {
    return {
      size: [1080, 1350], theme,
      build: () => scatter(1080, 1350, "heart", 10, 17) + col("84px 80px 60px",
        K(kicker) + H(headline, 108, 900, 16) + GAP(34) +
        list.map(([t, d]) => `<div class="row" style="gap:34px;width:920px;margin-bottom:34px;text-align:left"><span class="num">${list.findIndex((x) => x[0] === t) + 1}</span><div><div class="headline" style="font-size:58px">${t}</div><div class="sub" style="font-size:31px;margin-top:6px">${d}</div></div></div>`).join("") +
        SPACE + U(30) + GAP(18) + logo(36)),
    };
  }

  // TikTok 9:16. Keep everything between y 230 and y 1450 and 110 px in from the sides: the app draws its caption,
  // buttons and progress bar over the rest.
  const TT = "230px 120px 480px";
  function slide({ theme = "", kicker, headline, size = 120, sub, still, pose, deco = "heart", end }) {
    return {
      size: [1080, 1920], theme,
      build: () => scatter(1080, 1920, deco, 16, 21) + col(TT,
        (kicker ? K(kicker, 30) : "") + H(headline, size, 840, kicker ? 22 : 0) + SPACE +
        (still ? phone(still, 380) : pose ? `<div class="lettie" data-pose="${pose}" style="width:420px;height:420px"></div>` : "") +
        (sub ? SPACE + S(sub, 820, 38) : "") + (end ? GAP(26) + U(34) : "") + SPACE),
    };
  }

  // A square (1080 x 1080) card of the Meta carousel ad
  function adSquare({ theme = "t-halloween", kicker, headline, size = 92, sub, still, pose, end }) {
    return {
      size: [1080, 1080], theme,
      build: () => scatter(1080, 1080, "halloween", 12, 51) + col("70px 80px 60px",
        (kicker ? K(kicker, 26) : "") + H(headline, size, 880, kicker ? 14 : 0) + SPACE +
        (still ? phone(still, 300) : pose ? `<div class="lettie" data-pose="${pose}" style="width:300px;height:300px"></div>` : "") +
        (sub ? SPACE + S(sub, 820, 32) : "") + (end ? GAP(20) + U(32) : "") + SPACE),
    };
  }

  // ---- profile pictures and covers ------------------------------------------------------------------------------

  const CARDS = {
    // Facebook Page profile picture: shown as a circle (cropped from the 1080 square), so the mark sits well inside it.
    "fb-profile": { size: [1080, 1080], theme: "", build: () => abs(0, 0, `<div style="width:1080px;height:1080px;background:radial-gradient(circle at 50% 42%, #5a2146 0%, #2a1220 52%, #160a14 100%)"></div>`) +
      abs(290, 270, `<img src="/public/logo-mark.svg" width="500" height="500" alt="" style="filter:drop-shadow(0 30px 50px rgba(224,96,126,.45))" />`) +
      abs(0, 800, `<div style="width:1080px;text-align:center" class="headline"><span style="font-size:76px;color:var(--text)">Love of My Life</span></div>`) },
    // The same mark on its own, for TikTok (also a circle) and small sizes where words would be unreadable.
    "tt-profile": { size: [1080, 1080], theme: "", build: () => abs(0, 0, `<div style="width:1080px;height:1080px;background:radial-gradient(circle at 50% 45%, #5a2146 0%, #2a1220 55%, #160a14 100%)"></div>`) +
      abs(230, 230, `<img src="/public/logo-mark.svg" width="620" height="620" alt="" style="filter:drop-shadow(0 30px 50px rgba(224,96,126,.45))" />`) },
    // Alternate profile picture with Lettie, the mascot.
    "profile-lettie": { size: [1080, 1080], theme: "", build: () => abs(0, 0, `<div style="width:1080px;height:1080px;background:radial-gradient(circle at 50% 45%, #5a2146 0%, #2a1220 55%, #160a14 100%)"></div>`) + lettie(160, 140, 760, "hello") },
    // Facebook cover 1640x624 (shown at 820x312 on a computer). A phone crops the sides and the profile picture covers the
    // bottom left, so the words sit in the middle.
    "fb-cover": { size: [1640, 624], theme: "", build: () => scatter(1640, 624, "heart", 16, 31) +
      abs(430, 92, `<div style="width:780px;text-align:center"><div class="kicker" style="font-size:24px">For monthsaries, anniversaries and everything in between</div>${H("A love letter only they can open.", 86, 780, 14)}${S("Your photos, your song and your words, locked with a question only they can answer.", 700, 28, 14).replace('class="sub"', 'class="sub" style="margin-left:auto;margin-right:auto"')}<div style="margin-top:22px"><span class="url" style="font-size:26px;padding:12px 26px">${URL_TEXT}</span></div></div>`) +
      lettie(1250, 150, 340, "hello") + abs(60, 90, phone("envelope", 210, "transform:rotate(-8deg)")) },
    // Cover for OFW season: same size and safe area, different words.
    "fb-cover-ofw": { size: [1640, 624], theme: "t-navy", build: () => scatter(1640, 624, "spark", 16, 33) +
      abs(430, 92, `<div style="width:780px;text-align:center"><div class="kicker" style="font-size:24px">For OFWs and long-distance couples</div>${H("Miles apart. One link away.", 92, 780, 14)}<div class="sub" style="font-size:28px;width:700px;margin:14px auto 0">Send a letter they can open any time they miss you.</div><div style="margin-top:22px"><span class="url" style="font-size:26px;padding:12px 26px">${URL_TEXT}</span></div></div>`) +
      abs(1290, 200, `<div class="orn" style="width:230px;color:var(--gold);opacity:.9">${PLANE}</div>`) + lettie(60, 190, 300, "hello") },

    // ---- Facebook feed: the services ----
    "svc-partner": service({ occ: "partner", kicker: "Boyfriend · Girlfriend", headline: "Sweeter than a “happy monthsary” chat.", stills: ["envelope", "scratch"], sub: "Your photos, your song and a letter on a page only they can open.", designs: ["Secret Letter", "Will You Be Mine", "Date Night"] }),
    "svc-anniversary": service({ occ: "anniversary", kicker: "Anniversary", headline: "Another year of choosing each other.", stills: ["timeline", "storybook"], sub: "Put every year in one page, written in the stars or told chapter by chapter.", designs: ["Our Story", "Years Together", "Written in the Stars"] }),
    "svc-wedding": service({ occ: "wedding", kicker: "Wedding", headline: "For the day, the vows and the forever after.", stills: ["envelope", "stars"], sub: "Sealed invitations, your vows, a film of your photos, even a guest game.", designs: ["Our Vows", "The Invitation", "Guest Game"] }),
    "svc-birthday": service({ occ: "birthday", kicker: "Birthday", headline: "Skip the generic greeting.", stills: ["gift", "scratch"], sub: "A page made only for them: scratch to win, open the box, make a wish.", designs: ["Birthday Card", "Scratch and Win", "Make a Wish"] }),
    "svc-invitation": service({ occ: "invitation", kicker: "Invitations", headline: "Invite them in style.", stills: ["envelope", "cinematic"], sub: "Debuts, weddings, christenings, baby showers, reunions and parties: ten invitations to open.", designs: ["Debut", "Save the Date", "Baby Shower"] }),

    // ---- Facebook feed: the plans (prices checked against config/saas.php) ----
    "plan-sweet": price({ plan: "Sweet", line: "Your page, online.", price: "₱200", per: "a month", alt: "or ₱2,400 for a year", ticks: ["12 photos", "5 templates", "Your link and QR code"], note: "Build and preview free. Pay when you are ready to send it." }),
    "plan-sweetheart": price({ theme: "t-gold", plan: "Sweetheart", line: "All ten ways to open it.", price: "₱350", per: "a month", alt: "or ₱4,200 for a year", ticks: ["40 photos", "All 10 templates", "Link and printable QR code"], note: "Build and preview free. Pay when you are ready to send it." }),
    "plan-forever": price({ theme: "t-ivory", plan: "Forever", line: "Pay once. Keep it for good.", price: "₱4,999", per: "one time, no renewals", ticks: ["60 photos", "All 10 templates", "Link and printable QR code", "Stays online for good"], note: "Build and preview free. Pay when you are ready to send it." }),
    "plan-photos": { size: [1080, 1080], theme: "", build: () => scatter(1080, 1080, "heart", 12, 19) + col("84px 80px 60px",
      K("Photo packs") + H("Too many memories? Add more room.", 100, 900, 18) + SPACE +
      `<div class="panel" style="width:760px"><div class="price" style="font-size:130px;color:var(--gold)">₱100</div><div class="sub" style="font-size:38px;margin-top:8px">for 12 more photos</div><div class="sub" style="font-size:30px;margin-top:10px">Yours for good, shared by all your pages.</div></div>` +
      SPACE + U(30) + GAP(10)) },

    // ---- Facebook feed: audiences ----
    "ofw-miles": statement({ theme: "t-navy", kicker: "For OFWs", headline: "Miles apart. One link away.", sub: "Make a page of your photos, your song and your words. They open it any time they miss you.", mark: PLANE, deco: "spark" }),
    "ofw-timezone": statement({ theme: "t-navy", kicker: "For OFWs", headline: "Different time zones. Same heart.", sub: "Make it tonight. They open it when they wake up, and again whenever they need you.", mark: PLANE, deco: "spark", h: 1350, size: 120 }),
    "ofw-taglish": statement({ theme: "t-navy", kicker: "Para sa mga OFW", headline: "Malayo ka man, andito pa rin ang puso mo.", size: 104, sub: "Gawan mo siya ng sulat na pwede niyang balikan tuwing namimiss ka niya.", pose: "love", deco: "spark" }),
    "couples-monthsary": statement({ kicker: "Monthsary coming up?", headline: "Give them a page they will keep.", sub: "Add a new letter every monthsary. Same link, same QR, more memories.", pose: "hello", h: 1350 }),
    "couples-ldr": statement({ kicker: "Long distance", headline: "Write the letter you would say in person.", size: 100, sub: "Then lock it with a question only the two of you know.", pose: "idle" }),
    "teen-first": statement({ kicker: "Your first monthsary", headline: "Make it one you will remember.", sub: "Pick a design, add your photos and a song, and write from the heart. Free to build and preview.", pose: "hello", h: 1350 }),
    "teen-ask": statement({ kicker: "Ask them out", headline: "Scratch the card to find the question.", size: 108, sub: "A sweet way to ask: they scratch, they smile, they answer.", pose: "love" }),

    // ---- Facebook feed: engagement ----
    "poll-open": poll({ kicker: "Which one is yours?", headline: "Stars or envelope?", a: { still: "stars", label: "Stars" }, b: { still: "envelope", label: "Envelope" }, ask: "Comment 1 or 2, then tag who you would send it to." }),
    "poll-gift": poll({ kicker: "This or that", headline: "Scratch card or surprise box?", a: { still: "scratch", label: "Scratch" }, b: { still: "gift", label: "Gift box" }, ask: "Comment 1 or 2: how would you want to be surprised?" }),
    "fill-song": statement({ kicker: "Finish the sentence", headline: "“Our song is ________.”", sub: "Tell us in the comments.", pose: "idle", deco: "heart" }),
    "tag-person": statement({ kicker: "Tag them", headline: "Tag the person who deserves a love letter.", size: 104, sub: "Then make it for them tonight.", pose: "hello", h: 1350 }),
    "quote-remember": statement({ theme: "t-cream", kicker: "Lettie says", headline: "The best gifts say “I remember.”", sub: "", mark: HEART }),
    "quote-distance": statement({ theme: "t-navy", kicker: "Lettie says", headline: "Distance tests love. Words keep it warm.", size: 100, sub: "", mark: PLANE, deco: "spark" }),
    "quote-taglish": statement({ theme: "t-gold", kicker: "Sabi ni Lettie", headline: "Hindi lang “HBD” sa chat. Sulat na maaalala niya.", size: 96, sub: "", mark: SPARK, deco: "spark" }),
    "how-3-steps": steps({ kicker: "How it works", headline: "Three steps, one evening.", list: [["Build it free", "Photos, a song and your letter."], ["Lock it", "A question only they can answer."], ["Send it", "A link, or a QR code on a card."]] }),
    "free-first": statement({ kicker: "No pressure", headline: "Build it free. Pay only when you send it.", size: 106, sub: "Make the whole page, try all ten templates and preview it before you choose a plan.", pose: "hello", h: 1350 }),
    "qr-card": statement({ theme: "t-cream", kicker: "Make it a gift", headline: "Print the QR on a card.", size: 120, sub: "Tuck it in a bouquet, a box or a book. They scan, they smile.", mark: HEART }),
    "soon": statement({ kicker: "Coming soon", headline: "Something sweet is on its way.", size: 118, sub: "A love letter they unlock with a question only they can answer.", pose: "hello", h: 1350 }),
    "live": statement({ kicker: "Now open", headline: "Got something sweet to say?", size: 118, sub: "Make them a page of their own. Free to build and preview.", pose: "hello", h: 1350 }),

    // ---- TikTok: video covers (9:16) ----
    "tt-cover-pov": slide({ kicker: "POV", headline: "You open a love letter only you can unlock", size: 104, still: "envelope" }),
    "tt-cover-ofw": slide({ theme: "t-navy", kicker: "OFW tip", headline: "3 ways to say “I miss you” from abroad", size: 104, still: "stars", deco: "spark" }),
    "tt-cover-build": slide({ kicker: "Build with me", headline: "Making my girlfriend a page", size: 112, still: "scratch" }),
    "tt-cover-which": slide({ theme: "t-party", kicker: "Which are you?", headline: "Envelope, scratch or stars?", size: 112, still: "gift", deco: "confetti" }),
    "tt-cover-200": slide({ theme: "t-gold", kicker: "Monthsary gift idea", headline: "A gift from ₱200 a month", size: 116, still: "timeline", deco: "spark" }),
    "tt-cover-rate": slide({ kicker: "Rate it", headline: "Rate my love letter 💌", size: 120, pose: "hello" }),
    "tt-cover-invite": slide({ theme: "t-navy", kicker: "Invitation reveal", headline: "Scratch to see the date", size: 112, still: "scratch", deco: "spark" }),
    "tt-cover-intro": slide({ kicker: "Hi, I am Lettie", headline: "Your little love letter", size: 120, pose: "hello" }),

    // ---- TikTok photo-mode carousels: post the five slides in order ----
    "car-ofw-1": slide({ theme: "t-navy", kicker: "For OFWs · 1/5", headline: "Missing them is the hardest part.", size: 112, pose: "idle", deco: "spark" }),
    "car-ofw-2": slide({ theme: "t-navy", kicker: "2/5", headline: "Time zones get in the way.", size: 112, sub: "Your good morning is their goodnight.", deco: "spark" }),
    "car-ofw-3": slide({ theme: "t-navy", kicker: "3/5", headline: "So send a letter, not just a text.", size: 108, still: "envelope", deco: "spark" }),
    "car-ofw-4": slide({ theme: "t-navy", kicker: "4/5", headline: "Lock it with a question only they know.", size: 104, still: "stars", deco: "spark" }),
    "car-ofw-5": slide({ theme: "t-navy", headline: "Make it tonight. Free to build.", size: 112, pose: "love", deco: "spark", end: true }),
    "car-monthsary-1": slide({ kicker: "Monthsary · 1/5", headline: "Another month of you.", size: 120, pose: "love" }),
    "car-monthsary-2": slide({ kicker: "2/5", headline: "Skip the “HBD” in the chat.", size: 112, sub: "Say it properly this time." }),
    "car-monthsary-3": slide({ kicker: "3/5", headline: "Add your photos and your song.", size: 108, still: "classic" }),
    "car-monthsary-4": slide({ kicker: "4/5", headline: "Write what you never say out loud.", size: 108, still: "storybook" }),
    "car-monthsary-5": slide({ headline: "Send it. Watch them smile.", size: 116, pose: "hello", end: true }),
    "car-templates-1": slide({ kicker: "10 templates · 1/5", headline: "Choose how they open it.", size: 116, pose: "hello" }),
    "car-templates-2": slide({ kicker: "2/5 · Envelope", headline: "Break the seal.", size: 120, still: "envelope" }),
    "car-templates-3": slide({ kicker: "3/5 · Scratch", headline: "Scratch the card.", size: 120, still: "scratch" }),
    "car-templates-4": slide({ kicker: "4/5 · Stars", headline: "Light up the stars.", size: 120, still: "stars" }),
    "car-templates-5": slide({ headline: "Which one is theirs?", size: 120, pose: "idle", end: true }),

    // ---- Halloween (offered 17-31 October): pictures for the page, the ad carousel and the stories. Keep the words honest:
    //      the letter, song and design are free to build; photos come with a plan. ----
    "hw-hook": { size: [1080, 1350], theme: "t-halloween", build: () => scatter(1080, 1350, "halloween", 12, 31) + col("84px 80px 64px",
      K("Halloween") + H("Make this Halloween unforgettable.", 108, 900, 20) + SPACE +
      `<div class="lettie" data-pose="hello" style="width:360px;height:360px"></div>` + SPACE +
      S("A haunted door they knock on, a letter only they can open.", 860, 34) + GAP(26) + U(30) + GAP(10)) },
    "hw-door": { size: [1080, 1350], theme: "t-halloween", build: () => scatter(1080, 1350, "halloween", 12, 33) + col("78px 70px 60px",
      K("Trick or treat") + H("Knock three times.", 112, 900, 18) + GAP(26) + phone("halloween", 380) + GAP(26) +
      S("A haunted door that creaks open on your photos and your letter.", 860, 32) + SPACE +
      `<div class="row" style="gap:28px">${logo(36)}<span class="url" style="font-size:26px;padding:12px 24px">${URL_TEXT}</span></div>`) },
    "hw-lock": { size: [1080, 1350], theme: "t-halloween", build: () => scatter(1080, 1350, "halloween", 12, 35) + col("78px 70px 60px",
      K("Spooky and sweet") + H("Lock it with a question only they know.", 92, 900, 14) + GAP(20) + phone("halloween", 340) + GAP(20) +
      chips(["🎃 Halloween template", "3D lock screen", "Your photos and song"]) + SPACE +
      `<div class="row" style="gap:28px">${logo(36)}<span class="url" style="font-size:26px;padding:12px 24px">${URL_TEXT}</span></div>`) },
    "hw-steps": steps({ theme: "t-halloween", kicker: "Halloween in 3 steps", headline: "Spooky, sweet, sent.", list: [
      ["Write the letter", "Your words, your photos and a song."],
      ["Pick Halloween", "A haunted door, three knocks, a treat inside."],
      ["Send the link", "They knock. The door opens. Boo."],
    ] }),
    "hw-poll": { size: [1080, 1350], theme: "t-halloween", build: () => scatter(1080, 1350, "halloween", 10, 37) + col("78px 70px 60px",
      K("Be honest") + H("Trick or treat?", 120, 920, 18) + GAP(40) +
      `<div class="row" style="gap:60px;align-items:flex-start">` +
      [["🎃", "Trick"], ["🍬", "Treat"]].map(([e, t], i) => `<div style="display:flex;flex-direction:column;align-items:center;gap:18px"><div class="panel" style="width:380px;padding:60px 20px"><div style="font-size:150px;line-height:1">${e}</div></div><div class="row" style="gap:14px"><span class="num" style="width:64px;height:64px;font-size:40px">${i + 1}</span><span class="hand" style="font-size:54px">${t}</span></div></div>`).join("") +
      `</div>` + SPACE + S("Comment 1 or 2, then tag who you would send a haunted love letter to.", 880, 36) + GAP(20) + logo(34)) },

    // Meta carousel ad: five square cards, swiped in order (1:1 is the format Meta shows without cropping)
    "ad-hw-1": adSquare({ kicker: "Halloween · 1/5", headline: "Boo! A love letter with a twist.", size: 92, pose: "hello" }),
    "ad-hw-2": adSquare({ kicker: "2/5", headline: "They knock three times.", size: 96, still: "halloween" }),
    "ad-hw-3": adSquare({ kicker: "3/5", headline: "The door creaks open.", size: 96, sub: "On your photos, your song and your letter." }),
    "ad-hw-4": adSquare({ kicker: "4/5", headline: "Locked with a question only they know.", size: 80, still: "halloween" }),
    "ad-hw-5": adSquare({ headline: "Start free. Send it before the 31st.", size: 84, sub: "Halloween template with Sweetheart or Forever.", pose: "love", end: true }),
    // Single ads: the feed link ad (1.91:1) and the story / reel ad (9:16, words inside the safe zone)
    "ad-hw-feed": { size: [1200, 628], theme: "t-halloween", build: () => scatter(1200, 628, "halloween", 8, 41) +
      abs(60, 70, K("Halloween", 24) + H("Trick or treat?<br>Send a haunted love letter.", 64, 620, 14) + S("Knock three times. The door opens on your letter.", 560, 28, 18) + GAP(22) + `<span class="url" style="font-size:26px">${URL_TEXT}</span>`, "text-align:left") +
      abs(840, 52, phone("halloween", 250, "transform:rotate(4deg)")) },
    "ad-hw-story": slide({ theme: "t-halloween", kicker: "Halloween", headline: "Knock three times.", size: 128, still: "halloween", sub: "A haunted love letter, free to start.", deco: "halloween", end: true }),

    // TikTok / Reels photo-mode carousel (9:16)
    "car-hw-1": slide({ theme: "t-halloween", kicker: "Halloween · 1/5", headline: "A spooky-sweet love letter.", size: 112, pose: "hello", deco: "halloween" }),
    "car-hw-2": slide({ theme: "t-halloween", kicker: "2/5", headline: "They knock three times.", size: 116, still: "halloween", deco: "halloween" }),
    "car-hw-3": slide({ theme: "t-halloween", kicker: "3/5", headline: "The door creaks open.", size: 116, sub: "Your photos. Your song. Your words.", deco: "halloween" }),
    "car-hw-4": slide({ theme: "t-halloween", kicker: "4/5", headline: "Lock it with a question.", size: 112, still: "halloween", deco: "halloween" }),
    "car-hw-5": slide({ theme: "t-halloween", headline: "Send it before the 31st.", size: 112, pose: "love", deco: "halloween", end: true }),

    // ---- Interactive set (pairs with the interactive reels; see marketing/interactive/INTERACTIVE_CAMPAIGN.md) ----
    "ix-pick": { size: [1080, 1350], theme: "t-gold", build: () => scatter(1080, 1350, "heart", 10, 21) + col("78px 60px 60px",
      K("Choose your envelope") + H("Pick a letter.", 120, 940, 18) + GAP(46) +
      `<div class="row" style="gap:40px;align-items:flex-start">${[["1", "a promise"], ["2", "a memory"], ["3", "a surprise"]].map(([n, l]) => `<div style="display:flex;flex-direction:column;align-items:center;gap:22px;width:270px"><div class="env"><div class="flap"></div><div class="seal">♥</div></div><span class="num" style="width:84px;height:84px;font-size:52px">${n}</span><span class="hand" style="font-size:56px">${l}</span></div>`).join("")}</div>` +
      GAP(34) + S("Comment 1, 2 or 3 and Lettie will write you back.", 860, 38) + SPACE + U(30) + GAP(10)) },
    ...Object.fromEntries([["1", "A promise", "I promise to choose you on the busy days too."], ["2", "A memory", "Remember the last time you laughed so hard you forgot the joke? Keep that one close."], ["3", "A surprise", "Someone is thinking about you right now. Tell them first."]].map(([n, k, line]) => [`ix-pick-${n}`, { size: [1080, 1350], theme: "t-gold", build: () => scatter(1080, 1350, "heart", 10, 50 + +n) + col("78px 70px 60px",
      K(`Your letter: ${n}`) + H(k, 112, 940, 18) + GAP(44) +
      `<div class="paper ruled" style="width:860px;min-height:430px;padding:56px;display:grid;place-items:center"><div class="headline" style="font-size:70px;line-height:1.12">${line}</div></div>` +
      GAP(40) + S("Tag who should read this one.", 860, 38) + SPACE + U(30) + GAP(10)) }])),
    "ix-scratch-1": { size: [1080, 1350], theme: "", build: () => scatter(1080, 1350, "heart", 10, 22) + col("78px 70px 60px",
      K("Scratch and tag") + H("Scratch to reveal.", 112, 940, 18) + GAP(46) +
      `<div class="foil" style="width:860px;height:470px;display:grid;place-items:center"><span style="font-size:68px;font-weight:600;letter-spacing:.06em;color:rgba(120,70,10,.6)">SCRATCH HERE</span></div>` +
      GAP(40) + S("Who is getting your next love letter?", 860, 40) + SPACE + U(30) + GAP(10)) },
    "ix-scratch-2": { size: [1080, 1350], theme: "", build: () => scatter(1080, 1350, "heart", 10, 23) + col("78px 70px 60px",
      K("Revealed") + H("Tag them.", 124, 940, 18) + GAP(46) +
      `<div class="paper" style="position:relative;width:860px;height:470px;display:grid;place-items:center;overflow:hidden"><div style="position:absolute;left:0;top:0;width:260px;height:150px;background:linear-gradient(135deg,#f7d98c,#d9a441);clip-path:polygon(0 0,100% 0,60% 55%,0 100%)"></div><div style="position:absolute;right:0;bottom:0;width:220px;height:130px;background:linear-gradient(135deg,#d9a441,#f7d98c);clip-path:polygon(100% 0,100% 100%,0 100%,45% 45%)"></div><div class="headline" style="font-size:80px;width:640px;line-height:1.1">Tag the person you’d send it to 💌</div></div>` +
      GAP(40) + S("Then make it for them tonight. Free to build and preview.", 860, 36) + SPACE + U(30) + GAP(10)) },
    ...Object.fromEntries([["1", "💌", "Envelope", "🎟️", "Scratch card"], ["2", "✨", "Stars", "📖", "Storybook"], ["3", "🎵", "Our song", "📸", "Our photos"], ["4", "🌙", "Midnight", "☀️", "Morning"]].map(([n, e1, l1, e2, l2]) => [`ix-tt-${n}`, { size: [1080, 1350], theme: "t-party", build: () => scatter(1080, 1350, "confetti", 14, 30 + +n) + col("78px 70px 60px",
      K(`Round ${n} of 4`) + H("This or that?", 116, 940, 18) + GAP(56) +
      `<div class="row" style="gap:0"><div class="choice"><span class="n">1</span><span class="e">${e1}</span><span class="l">${l1}</span></div><div class="or">or</div><div class="choice"><span class="n">2</span><span class="e">${e2}</span><span class="l">${l2}</span></div></div>` +
      GAP(44) + S(n === "4" ? "Last one! Swipe for how to answer →" : "Comment 1 or 2. Swipe for the next round →", 860, 36) + SPACE + U(30) + GAP(10)) }])),
    "ix-tt-5": statement({ theme: "t-party", kicker: "Your turn", headline: "Comment your four.", size: 120, sub: "Like this: 1, 2, 1, 2. Then tag someone to see if you match.", pose: "hello", deco: "confetti", h: 1350 }),
    "ix-fill-1": { size: [1080, 1350], theme: "t-sunset", build: () => scatter(1080, 1350, "heart", 10, 24) + col("78px 70px 60px",
      K("Your turn") + H("Finish the sentence.", 112, 940, 18) + GAP(46) +
      `<div class="paper ruled" style="width:860px;height:480px;padding:60px 56px;text-align:left"><div class="hand" style="font-size:112px;line-height:1.1;color:var(--ink)">Our song is</div><div class="hand" style="font-size:96px;line-height:1.1;margin-top:26px;color:#d9466e">________</div></div>` +
      GAP(40) + S("Type yours in the comments.", 860, 40) + SPACE + U(30) + GAP(10)) },
    "ix-fill-2": { size: [1080, 1350], theme: "t-sunset", build: () => scatter(1080, 1350, "heart", 10, 25) + col("78px 70px 60px",
      K("Need ideas?") + H("“Our song is…”", 120, 940, 18) + GAP(46) +
      `<div style="display:flex;flex-direction:column;gap:22px;align-items:center">${["the one we sang in the car", "playing at 2 a.m.", "still on repeat"].map((t) => `<span class="pill" style="font-size:44px;padding:20px 40px">${t}</span>`).join("")}</div>` +
      GAP(44) + S("Now put your song on a page only they can open.", 860, 36) + SPACE + U(30) + GAP(10)) },
    "ix-quiz-cover": statement({ kicker: "30-second quiz", headline: "How should they open your love letter?", size: 100, sub: "💌 Envelope · 🎟️ Scratch card · 📖 Storybook · ✨ Stars. Which one are you?", pose: "hello", h: 1350 }),
    ...Object.fromEntries([["envelope", "💌", "The Sealed Envelope", "You say it with care.", "Break the seal.", "t-gold"], ["scratch", "🎟️", "The Scratch Card", "You love a little surprise and a big laugh.", "Scratch, smile, answer.", "t-party"], ["storybook", "📖", "The Storybook", "You remember everything.", "Turn the page.", "t-cream"], ["stars", "✨", "The Night Sky", "You dream out loud.", "Make a wish.", "t-navy"]].map(([k, e, name, line, hand, theme]) => [`ix-quiz-${k}`, { size: [1080, 1350], theme, build: () => scatter(1080, 1350, "spark", 12, 40) + col("78px 70px 60px",
      K("My love letter style") + `<div style="font-size:150px;line-height:1;margin-top:34px">${e}</div>` + H(name, 108, 940, 22) + GAP(24) + S(line, 860, 40) + GAP(22) + `<div class="hand" style="font-size:78px">${hand}</div>` + GAP(24) + S("Take the quiz and tag who’d get your letter.", 860, 32) + SPACE + U(30) + GAP(10)) }])),
  };

  // Lettie's poses: the same parts promo.js animates
  function pose(svg, name) {
    const set = (id, attr, value) => svg.querySelector(`#${id}`).setAttribute(attr, value);
    const arms = { hello: [22, -128], idle: [24, -24], love: [-34, 34] }[name];
    set("l-arm-l", "transform", `translate(76 238) rotate(${arms[0]})`);
    set("l-arm-r", "transform", `translate(324 238) rotate(${arms[1]})`);
    set("l-hug", "opacity", name === "love" ? "1" : "0");
    set("l-hug", "transform", "translate(200 338)");
    set("l-eyes", "transform", name === "love" ? "translate(0 262) scale(1 0.08) translate(0 -262)" : "");
    set("l-eyes-happy", "opacity", name === "love" ? "1" : "0");
    set("l-mouth-open", "opacity", name === "hello" ? "1" : "0");
    set("l-mouth-open", "transform", "translate(200 288) scale(1 0.9)");
    set("l-mouth-smile", "opacity", name === "hello" ? "0" : "1");
  }

  const name = new URLSearchParams(location.search).get("card") || "fb-profile";
  const def = CARDS[name];
  const [w, h] = def.size;
  const card = document.getElementById("card");
  card.style.width = `${w}px`;
  card.style.height = `${h}px`;
  if (def.theme) card.classList.add(def.theme);
  card.innerHTML = def.build();
  // the profile pictures draw their own background, so the card needs no padding for them
  window.cards = {
    names: Object.keys(CARDS),
    size: [w, h],
    ready: (async () => {
      const svgText = await fetch("/marketing/promo/lettie.svg").then((r) => r.text());
      for (const holder of card.querySelectorAll(".lettie")) {
        holder.innerHTML = svgText;
        pose(holder.querySelector("svg"), holder.dataset.pose);
      }
      await document.fonts.ready;
      await Promise.all(Array.from(card.querySelectorAll("img")).map((i) => i.decode().catch(() => { throw new Error(`image missing: ${i.src}`); })));
      return true;
    })(),
  };
})();
