// One picture per occasion for the week of 31 Oct - 6 Nov 2026. Lettie wears that occasion's outfit (looks/lettie-<look>.svg); the phone shows a still of
// the real demo page dressed as that occasion (footage.py -> out/promo/shots/stills/wk-<id>.jpg): sample content, not a customer's page.
(() => {
  const id = new URLSearchParams(location.search).get("p") || "birthday";
  const HEART = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-8-4.9-8-11.1C4 6.7 6.3 5 8.6 5c1.5 0 2.9.8 3.4 2 .5-1.2 1.9-2 3.4-2C17.7 5 20 6.7 20 9.9 20 16.1 12 21 12 21z"/></svg>';
  const SAMPLE = "Sample page shown. Free to build and preview.";
  const POSTS = {
    birthday: { theme: "t-party", kicker: "Birthday", head: "Make their<br>day unforgettable.", sub: "A surprise box that opens on your photos and your birthday wishes.", glyphs: ["🎉", "✨", "🎈"], note: `${SAMPLE} Gift template: Sweetheart or Forever.` },
    invitation: { theme: "t-navy", kicker: "Invitations", head: "You're<br>invited.", sub: "An invitation with no printing. Share a link or a QR code, and edit it any time.", glyphs: ["✦", "♥"], note: `${SAMPLE} Send it with a plan.` },
    wedding: { theme: "t-wedding", kicker: "Wedding", head: "For the day<br>you say I do.", sub: "Your story, page by page, in a little storybook for everyone who loves you.", glyphs: ["♥", "✦"], note: `${SAMPLE} Storybook template: Sweetheart or Forever.` },
    anniversary: { theme: "t-gold", kicker: "Anniversary", head: "Another year<br>together.", sub: "A star for every memory. Add your photos, your song and your words.", glyphs: ["✦", "♥"], note: `${SAMPLE} Stars template: Sweetheart or Forever.` },
    partner: { theme: "t-rose", kicker: "Boyfriend / Girlfriend", head: "Sweet on<br>someone?", sub: "Hide your message under a scratch card. They scratch, they smile.", glyphs: ["♥"], note: `${SAMPLE} Scratch template: Sweetheart or Forever.` },
  };
  const P = POSTS[id];
  if (!P) throw new Error(`unknown picture ${id}`);
  const card = document.getElementById("card");
  card.className = P.theme;
  const el = (cls, html = "") => { const n = document.createElement("div"); n.className = `abs ${cls}`; n.innerHTML = html; card.appendChild(n); return n; };
  const bits = el("bits");
  let seed = 11;
  const rand = () => ((seed = (seed * 16807) % 2147483647) - 1) / 2147483646;
  for (let k = 0; k < 22; k++) {
    const b = document.createElement("span");
    b.className = "bit";
    b.textContent = P.glyphs[k % P.glyphs.length];
    b.style.cssText = `left:${rand() * 1040}px;top:${rand() * 1300}px;font-size:${14 + rand() * 30}px;opacity:${0.12 + rand() * 0.22}`;
    bits.appendChild(b);
  }
  el("kicker txt", P.kicker);
  el("headline txt", P.head);
  el("sub txt", P.sub);
  el("phone", `<div class="screen"><img alt="" src="/marketing/out/promo/shots/stills/wk-${id}.jpg" /><span class="island"></span></div>`);
  const lettie = el("lettie");
  el("brand txt", `${HEART}<span>Love of My Life</span>`);
  el("url txt", "loveofmylife.love");
  el("note txt", P.note);
  el("qr", '<img alt="" src="/marketing/out/promo/qr.svg" />');
  const ready = (async () => {
    lettie.innerHTML = await fetch(`/marketing/social/week-nov/looks/lettie-${id}.svg`).then((r) => r.text());
    await Promise.all(["italic 600 40px \"Cormorant Garamond\"", "500 40px Inter", "600 40px Inter"].map((f) => document.fonts.load(f)));
    await document.fonts.ready;
    await Promise.all(Array.from(card.querySelectorAll("img")).map((i) => (i.complete ? null : i.decode())));
    return true;
  })();
  // Words must stay inside the picture and never touch the phone, Lettie, the QR code or each other.
  function audit() {
    const c = card.getBoundingClientRect(), problems = [], M = 60;
    const words = Array.from(card.querySelectorAll(".txt")).map((n) => [n, n.getBoundingClientRect()]);
    const l = lettie.getBoundingClientRect();
    const solid = [["the phone", card.querySelector(".phone").getBoundingClientRect()], ["the QR code", card.querySelector(".qr").getBoundingClientRect()],
      ["Lettie", { left: l.left + l.width * 0.1, right: l.right - l.width * 0.1, top: l.top + l.height * 0.08, bottom: l.bottom - l.height * 0.05 }]];
    words.forEach(([n, r], i) => {
      const name = n.textContent.trim().slice(0, 28);
      if (n.scrollWidth > n.clientWidth + 1) problems.push(`"${name}" is wider than its box`);
      if (r.left < c.left + M - 1 || r.right > c.right - M + 1 || r.top < c.top + 40 || r.bottom > c.bottom - 8) problems.push(`"${name}" outside the safe area`);
      for (const [what, s] of solid) if (r.left < s.right - 2 && r.right > s.left + 2 && r.top < s.bottom - 2 && r.bottom > s.top + 2) problems.push(`"${name}" overlaps ${what}`);
      words.slice(i + 1).forEach(([m, q]) => { if (r.left < q.right - 2 && r.right > q.left + 2 && r.top < q.bottom - 2 && r.bottom > q.top + 2) problems.push(`"${name}" overlaps "${m.textContent.trim().slice(0, 20)}"`); });
    });
    const phone = card.querySelector(".phone").getBoundingClientRect();
    if (phone.bottom > card.querySelector(".brand").getBoundingClientRect().top) problems.push("the phone reaches the footer");
    return problems;
  }
  window.post = { id, ready, audit };
})();
