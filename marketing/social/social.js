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
      const glyph = { heart: "♥", spark: "✦", ring: "◯" }[kind];
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
