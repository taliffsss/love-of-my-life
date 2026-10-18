// Template "gift": tap the present to untie it; the photos come out as a deck (tap, swipe or the buttons send the top
// photo to the back) next to the letter.
(window.LoveTemplates = window.LoveTemplates || {}).gift = {
  build(kit, content) {
    const byId = (id) => document.getElementById(id);
    const root = byId("gift"), box = byId("giftBox"), opened = byId("giftOpen"), section = byId("giftPhotos");
    const deck = byId("giftDeck"), prevBtn = byId("giftPrev"), nextBtn = byId("giftNext"), count = byId("giftCount");
    const photos = content.photos || [], recipient = content.recipient || "";
    const TILTS = [-4, 3, -2, 5, -3, 2];

    const sheet = kit.letterSheet(content);
    sheet.tabIndex = -1;
    byId("giftLetter").appendChild(sheet);

    const cards = photos.map((url, k) => {
      const card = kit.el("figure", "gift-card");
      card.style.setProperty("--tilt", `${TILTS[k % TILTS.length]}deg`);
      card.appendChild(kit.photo(url, `Photo ${k + 1} of ${photos.length} for ${recipient}`, k < 2));
      card.addEventListener("click", () => card.classList.contains("top") && step(1));
      deck.appendChild(card);
      return card;
    });
    if (!cards.length) section.remove();
    else opened.classList.add("with-photos");
    if (cards.length < 2) {
      prevBtn.hidden = true;
      nextBtn.hidden = true;
    }

    let order = cards.map((_, k) => k), moving = false;
    function stack() {
      order.forEach((k, pos) => {
        const card = cards[k];
        card.style.zIndex = String(cards.length - pos);
        card.classList.toggle("top", pos === 0);
        card.setAttribute("aria-hidden", pos === 0 ? "false" : "true");
      });
      if (cards.length) count.textContent = `${order[0] + 1} / ${cards.length}`;
    }
    async function step(dir) {
      if (moving || cards.length < 2) return;
      moving = true;
      if (dir > 0) {
        const card = cards[order[0]];
        card.classList.add("fly");
        await kit.wait(450);
        order.push(order.shift());
        card.classList.add("still");
        card.classList.remove("fly");
        stack();
        void card.offsetWidth;
        card.classList.remove("still");
      } else {
        order.unshift(order.pop());
        const card = cards[order[0]];
        card.classList.add("from-left");
        stack();
        void card.offsetWidth;
        card.classList.remove("from-left");
        await kit.wait(450);
      }
      moving = false;
      kit.announce(`Photo ${order[0] + 1} of ${cards.length}.`);
    }
    nextBtn.addEventListener("click", () => step(1));
    prevBtn.addEventListener("click", () => step(-1));
    kit.swipe(deck, (dir) => step(dir));
    section.addEventListener("keydown", (e) => {
      if (e.key === "ArrowRight") step(1);
      if (e.key === "ArrowLeft") step(-1);
    });
    stack();

    let open = false;
    async function unwrap() {
      if (open) return;
      open = true;
      root.classList.add("opening");
      kit.celebrate(box, 22);
      await kit.wait(1400);
      root.classList.add("open");
      opened.hidden = false;
      root.scrollTop = 0;
      kit.announce(cards.length ? `Unwrapped: ${cards.length} photos and a letter for ${recipient || "you"}.` : `Unwrapped: a letter for ${recipient || "you"}.`);
      (cards.length > 1 ? nextBtn : sheet).focus({ preventScroll: true });
    }
    box.addEventListener("click", unwrap);

    return { settle: () => box.focus({ preventScroll: true }) };
  }
};
