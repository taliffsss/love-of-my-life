// Invitation campaign cards, one per URL: invite.html?card=<name>. render_invite.py screenshots every entry of CARDS at its size.
// Needs the stills from shots.py (marketing/out/invitation/shots/) and Lettie's invitation looks (build_mascot.py).
// The phone screens are the real app with invented names; cards that show them say so.
(() => {
  const SHOTS = "/marketing/out/invitation/shots/";
  const URL_TEXT = "loveofmylife.love";
  const HEART = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-8-4.9-8-11.1C4 6.7 6.3 5 8.6 5c1.5 0 2.9.8 3.4 2 .5-1.2 1.9-2 3.4-2C17.7 5 20 6.7 20 9.9 20 16.1 12 21 12 21z"/></svg>';
  const logo = (size = 44) => `<div class="logo" style="font-size:${size}px">${HEART}<span>Love of My Life</span></div>`;
  const phone = (src, w) => `<div class="phone" style="width:${w}px;height:${Math.round((w - 24) / 0.4621 + 24)}px"><div class="screen">${src ? `<img src="${src}" alt="" />` : ""}<span class="island"></span></div></div>`;
  const at = (x, y, html, style = "") => `<div class="abs" style="left:${x}px;top:${y}px;${style}">${html}</div>`;
  // Lettie's invitation SVG is 560x400 (the card sticks out on the right); w is the width of the whole drawing
  const mascot = (x, y, w, look = "") => at(x, y, `<div class="lettie" data-look="lettie-invite${look}" style="width:${w}px;height:${Math.round((w * 400) / 560)}px"></div>`);
  function hearts(w, h, n = 14) {
    let seed = 7;
    const r = () => ((seed = (seed * 16807) % 2147483647) - 1) / 2147483646;
    return `<div class="hearts">${Array.from({ length: n }, () => `<span style="left:${(r() * w) | 0}px;top:${(r() * h) | 0}px;font-size:${(14 + r() * 26) | 0}px">♥</span>`).join("")}</div>`;
  }
  const centre = (w, html, style = "") => `<div style="width:${w}px;text-align:center;${style}">${html}</div>`;
  const STEPS = [
    ["1", "Pick a template", "Wedding, debut, christening, baby shower and more."],
    ["2", "Add photos and your message", "Your photos, the date, the place."],
    ["3", "Share the link or the QR code", "Send it in a message, or print the QR code on a card."],
  ];
  const steps = (w) => STEPS.map(([n, t, d]) => `<div class="step" style="width:${w}px"><div class="n">${n}</div><div><div class="t">${t}</div><div class="d">${d}</div></div></div>`).join("");

  // [width, height, build, transparent?, theme]
  const CARDS = {
    // feed posts (Facebook, Instagram)
    "inv-feed-hook": [1080, 1080, () => hearts(1080, 1080, 10) +
      at(80, 80, `<div class="headline" style="font-size:112px;width:920px">Tired of printing invitations?</div>`) +
      at(80, 400, `<div class="sub" style="width:470px">Make one online. Add your photos and your message, then share the link or the QR code.</div>`) +
      mascot(340, 520, 730) +
      at(80, 800, `<div class="url" style="font-size:30px;padding:14px 28px">${URL_TEXT}</div>`) +
      at(80, 940, logo(38)), false, "ivory"],
    "inv-feed-cost": [1080, 1080, () => hearts(1080, 1080, 10) +
      at(0, 90, centre(1080, `<div class="kicker">Less hassle</div><div class="headline" style="font-size:118px;width:900px;margin:14px auto 0">No printing. No reprinting.</div>`)) +
      at(140, 450, `<div class="sub" style="width:800px;text-align:center">Wrong time or a new venue? Edit your page. The link and the QR code stay the same.</div>`) +
      mascot(290, 575, 500, "-christening") +
      at(0, 960, centre(1080, `<div style="display:flex;justify-content:center;align-items:center;gap:30px">${logo(34)}<div class="url" style="font-size:26px;padding:12px 24px">${URL_TEXT}</div></div>`)), false, "ivory"],
    "inv-feed-steps": [1080, 1080, () => hearts(1080, 1080, 10) +
      at(70, 70, `<div class="kicker">Three steps</div><div class="headline" style="font-size:96px;width:560px;margin-top:10px">Your invitation, online</div>`) +
      at(70, 400, `<div style="display:flex;flex-direction:column;gap:44px">${steps(540)}</div>`) +
      at(690, 90, phone(SHOTS + "invitation-wedding-3-read.png", 330)) +
      at(70, 960, logo(34)) +
      at(690, 1000, `<div class="note" style="width:330px;text-align:center">Example with made-up names</div>`)],
    "inv-feed-occasions": [1080, 1350, () => hearts(1080, 1350, 12) +
      at(0, 60, centre(1080, `<div class="kicker">Invitation templates</div><div class="headline" style="font-size:92px;width:960px;margin:12px auto 0">Weddings, debuts, christenings and more</div>`)) +
      [["-wedding", "Wedding", "invitation-wedding-3-read"], ["-birthday", "Debut", "invitation-debut-2-open"], ["-christening", "Christening", "invitation-baptism-3-read"]]
        .map(([look, label, shot], i) => mascot(10 + i * 352, 400, 330, look) + at(10 + i * 352, 660, `<div class="label" style="width:330px">${label}</div>`) + at(10 + i * 352 + 45, 740, phone(SHOTS + shot + ".png", 240))).join("") +
      at(0, 1270, centre(1080, `<div style="display:flex;justify-content:center;align-items:center;gap:30px">${logo(34)}<div class="url" style="font-size:26px;padding:12px 24px">${URL_TEXT}</div></div>`)) +
      at(0, 1316, centre(1080, `<div class="note">Examples with made-up names</div>`))],
    // stories (9:16): keep everything important between y 250 and 1580
    "inv-story-hook": [1080, 1920, () => hearts(1080, 1920, 16) +
      at(70, 280, `<div class="headline" style="font-size:128px;width:940px">Tired of printing invitations?</div>`) +
      mascot(100, 690, 880) +
      at(100, 1380, `<div class="sub" style="width:880px;text-align:center;font-size:42px">Add your photos and your message. Share the link or the QR code.</div>`) +
      at(0, 1535, centre(1080, `<div class="url" style="font-size:38px;padding:18px 36px">${URL_TEXT}</div>`)) +
      at(0, 1650, centre(1080, `<div style="display:flex;justify-content:center">${logo(40)}</div>`)), false, "ivory"],
    "inv-story-steps": [1080, 1920, () => hearts(1080, 1920, 16) +
      at(0, 270, centre(1080, `<div class="kicker">Three steps</div><div class="headline" style="font-size:104px;width:920px;margin:12px auto 0">Your invitation, online</div>`)) +
      at(70, 600, `<div style="display:flex;flex-direction:column;gap:52px">${steps(940)}</div>`) +
      mascot(250, 1150, 600, "-wedding") +
      at(0, 1640, centre(1080, `<span class="url">${URL_TEXT}</span>`))],
    // link preview (Facebook / Messenger)
    "inv-link-preview": [1200, 630, () => hearts(1200, 630, 8) +
      mascot(560, 130, 620) +
      at(60, 70, logo(48)) +
      at(60, 190, `<div class="headline" style="font-size:78px;width:560px">Invitations, made online</div>`) +
      at(60, 430, `<div class="sub" style="width:470px;font-size:30px">Add photos and a message. Share a link or QR code.</div>`) +
      at(60, 530, `<div class="url" style="font-size:28px;padding:12px 26px">${URL_TEXT}</div>`), false, "ivory"],
    // the invitation video (compose.py): backgrounds, the phone's bezel with a see-through screen, and the captions
    "vid-s1": [1080, 1920, () => hearts(1080, 1920, 14) +
      at(70, 270, `<div class="headline" style="font-size:136px;width:940px;text-align:center">Tired of printing invitations?</div>`), false, "ivory"],
    "vid-bg-dark": [1080, 1920, () => hearts(1080, 1920, 16)],
    "vid-bezel": [1080, 1920, () => at(280, 230, phone("", 520)), true, "bezel"],
    "vid-s5": [1080, 1920, () => hearts(1080, 1920, 14) +
      at(0, 250, centre(1080, `<div class="headline" style="font-size:140px;width:900px;margin:0 auto">Invite, don’t print.</div>`)) +
      at(0, 1230, centre(1080, `<div class="sub" style="font-size:44px;width:860px;margin:0 auto">Free to build and preview. Send it with a plan.</div>`)) +
      at(0, 1420, centre(1080, `<span class="url" style="font-size:42px;padding:18px 38px">${URL_TEXT}</span>`)) +
      at(0, 1700, centre(1080, `<div style="display:flex;justify-content:center">${logo(38)}</div>`)), false, "ivory"],
    ...Object.fromEntries([
      ["vid-cap-2", "Make one online. Add your photos and your message."],
      ["vid-cap-3", "Weddings, debuts, christenings, and more."],
      ["vid-cap-4", "Share the link, or print the QR code on a card."],
      ["vid-cap-5", "Less printing. Start free at loveofmylife.love"],
    ].map(([key, text]) => [key, [1080, 260, () => at(0, 20, centre(1080, `<span class="cap">${text}</span>`)), true]])),
    // Lettie's invitation looks: transparent stickers, and a character sheet
    "lettie-invite": [1080, 800, () => mascot(0, 0, 1080), true],
    "lettie-invite-wedding": [1080, 800, () => mascot(0, 0, 1080, "-wedding"), true],
    "lettie-invite-birthday": [1080, 800, () => mascot(0, 0, 1080, "-birthday"), true],
    "lettie-invite-christening": [1080, 800, () => mascot(0, 0, 1080, "-christening"), true],
    "lettie-invite-sheet": [2200, 780, () => hearts(2200, 780, 14) +
      at(0, 30, centre(2200, `<span class="hand" style="font-size:96px">Lettie</span> <span class="kicker" style="margin-left:18px">invites you</span>`)) +
      [["", "Invitation"], ["-wedding", "Wedding"], ["-birthday", "Debut and birthday"], ["-christening", "Christening"]]
        .map(([look, label], i) => mascot(10 + i * 548, 170, 530, look) + at(10 + i * 548, 585, `<div class="label" style="width:530px">${label}</div>`)).join(""), false, "ivory"],
  };

  const name = new URLSearchParams(location.search).get("card") || "inv-feed-hook";
  const [w, h, build, clear, theme] = CARDS[name];
  const card = document.getElementById("card");
  card.style.width = `${w}px`;
  card.style.height = `${h}px`;
  if (clear) card.classList.add("clear");
  if (theme) card.classList.add(theme);
  card.innerHTML = build();
  window.cards = {
    names: Object.keys(CARDS),
    size: [w, h],
    clear: !!clear,
    ready: (async () => {
      for (const holder of card.querySelectorAll(".lettie")) {
        holder.innerHTML = await fetch(`/marketing/invitation/${holder.dataset.look}.svg`).then((r) => r.text());
      }
      await document.fonts.ready;
      await Promise.all(Array.from(card.querySelectorAll("img")).map((i) => i.decode().catch(() => { throw new Error(`image missing: ${i.src}`); })));
      return true;
    })(),
  };
})();
