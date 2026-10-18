// Template "memory": flip two cards at a time to find the pairs. Up to six photos make the pairs (symbols fill in when
// there are fewer than four); when every pair is found, or the game is skipped, the letter and the photos open below.
(window.LoveTemplates = window.LoveTemplates || {}).memory = {
  build(kit, content) {
    const byId = (id) => document.getElementById(id);
    const root = byId("mem"), grid = byId("memGrid"), score = byId("memScore"), skip = byId("memSkip"), after = byId("memAfter");
    const photos = content.photos || [], recipient = content.recipient || "";
    const SYMBOLS = [["♥", "a heart"], ["★", "a star"], ["✿", "a flower"], ["☾", "a moon"], ["✦", "a sparkle"], ["♪", "a note"]];
    const pairs = Math.max(4, Math.min(6, photos.length));
    const faces = [];
    for (let k = 0; k < pairs; k++) {
      faces.push(k < photos.length ? { key: `p${k}`, photo: photos[k], label: `photo ${k + 1}` } : { key: `s${k}`, sym: SYMBOLS[k % SYMBOLS.length] });
    }
    const deck = faces.flatMap((f) => [f, f]);
    for (let i = deck.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [deck[i], deck[j]] = [deck[j], deck[i]];
    }
    const cols = deck.length === 10 ? 5 : 4;
    grid.style.setProperty("--cols", String(cols));
    grid.style.setProperty("--rows", String(Math.ceil(deck.length / cols)));

    let first = null, busy = false, found = 0, moves = 0, over = false;
    const cards = deck.map((face, n) => {
      const card = kit.el("button", "mem-card");
      card.type = "button";
      card._face = face;
      const inner = kit.el("span", "mem-inner");
      const back = kit.el("span", "mem-back", "♥");
      const front = kit.el("span", "mem-front");
      if (face.photo) front.appendChild(kit.photo(face.photo, "", n < 4));
      else front.appendChild(kit.el("span", "mem-sym", face.sym[0]));
      back.setAttribute("aria-hidden", "true");
      front.setAttribute("aria-hidden", "true");
      inner.append(back, front);
      card.appendChild(inner);
      card._n = n + 1;
      label(card);
      card.addEventListener("click", () => pick(card));
      grid.appendChild(card);
      return card;
    });
    function label(card) {
      const what = card._face.photo ? card._face.label : card._face.sym[1];
      const state = card.classList.contains("matched") ? `${what}, matched` : card.classList.contains("up") ? what : "face down";
      card.setAttribute("aria-label", `Card ${card._n}: ${state}`);
    }
    function flip(card, up) {
      card.classList.toggle("up", up);
      label(card);
    }
    function tally() {
      score.textContent = `${found} of ${pairs} pairs · ${moves} ${moves === 1 ? "move" : "moves"}`;
    }
    function pick(card) {
      if (busy || over || card === first || card.classList.contains("up")) return;
      flip(card, true);
      if (!first) {
        first = card;
        return;
      }
      moves++;
      const a = first, b = card;
      first = null;
      if (a._face.key === b._face.key) {
        for (const c of [a, b]) {
          c.classList.add("matched");
          label(c);
        }
        found++;
        tally();
        kit.celebrate(b, 10);
        if (found === pairs) finish(true);
        else kit.announce(`A pair! ${found} of ${pairs}.`);
        return;
      }
      tally();
      busy = true;
      kit.announce("Not a pair.");
      setTimeout(() => {
        flip(a, false);
        flip(b, false);
        busy = false;
      }, kit.reduced ? 700 : 950);
    }

    const sheet = kit.letterSheet(content);
    sheet.tabIndex = -1;
    after.appendChild(sheet);
    if (photos.length) after.appendChild(kit.prints(photos, recipient));
    async function finish(won) {
      if (over) return;
      over = true;
      root.classList.add("won");
      skip.hidden = true;
      for (const c of cards) flip(c, true);
      if (won) {
        kit.announce(`Every pair found in ${moves} moves! Your letter is open below.`);
        kit.celebrate(grid, 28);
        await kit.wait(1100);
      }
      after.hidden = false;
      await kit.wait(200);
      sheet.scrollIntoView({ behavior: kit.reduced ? "auto" : "smooth", block: "start" });
      sheet.focus({ preventScroll: true });
    }
    skip.addEventListener("click", () => finish(false));
    tally();

    return { settle: () => cards[0]?.focus({ preventScroll: true }) };
  }
};
