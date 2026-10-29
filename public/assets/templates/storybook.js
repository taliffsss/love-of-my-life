// Template "storybook": a book whose pages turn (buttons, arrow keys, a swipe, or a tap on a page).
// Page 0 is the cover, pages 1..n the chapters (photo i with paragraph i), the last one the end. A wide screen shows a
// chapter as a spread (photo left, words right); a phone shows one page with the photo above the words.
(window.LoveTemplates = window.LoveTemplates || {}).storybook = {
  build(kit, content) {
    const byId = (id) => document.getElementById(id);
    const book = byId("book"), left = byId("pageLeft"), right = byId("pageRight");
    const prevBtn = byId("bookPrev"), nextBtn = byId("bookNext"), count = byId("bookCount");
    const recipient = content.recipient || "";
    const { list, rest, total: photoCount } = kit.chapters(content, 12);
    const pages = list.length + 2;
    const wide = matchMedia("(min-width: 760px)");
    let at = 0, turning = false;

    const side = (cls) => kit.el("div", `side ${cls}`);
    const photoOf = (ch) => {
      const frame = kit.el("div", "side-frame");
      frame.appendChild(ch.photo ? kit.photo(ch.photo, `Photo ${ch.i + 1} of ${photoCount} for ${recipient}`, true) : kit.placeholder(ch.i));
      return frame;
    };
    const wordsOf = (ch) => {
      const words = kit.el("div", "side-text");
      words.appendChild(ch.text ? kit.el("p", "", ch.text) : kit.el("span", "side-orn", "♥"));
      return words;
    };
    function cover() {
      const s = side("side-cover");
      s.append(kit.el("p", "eyebrow", kit.invitation(content) ? "You are invited" : "A story for"), kit.el("h2", "", recipient), kit.el("span", "side-orn", "♥"));
      return s;
    }
    function restOfPhotos() {
      const s = side("side-rest");
      if (rest.length) s.append(kit.el("h3", "", "More photos"), kit.prints(rest, recipient, photoCount - rest.length, photoCount));
      else {
        s.classList.add("side-end");
        s.appendChild(kit.el("span", "side-orn", "♥"));
      }
      return s;
    }
    function theEnd(withRest) {
      const s = side("side-end");
      s.append(kit.el("p", "eyebrow", "The end"), kit.el("p", "end-name", recipient), kit.el("span", "side-orn", "♥"));
      if (withRest && rest.length) s.appendChild(kit.prints(rest, recipient, photoCount - rest.length, photoCount));
      return s;
    }
    // [left, right] on a wide screen; [null, page] on a phone
    function sides(i) {
      const ch = list[i - 1];
      if (wide.matches) {
        if (i === 0) return [null, cover()];
        if (ch) {
          const p = side("side-photo");
          p.appendChild(photoOf(ch));
          const t = side("");
          t.appendChild(wordsOf(ch));
          return [p, t];
        }
        return [restOfPhotos(), theEnd(false)];
      }
      if (i === 0) return [null, cover()];
      if (ch) {
        const both = side("side-both");
        both.append(photoOf(ch), wordsOf(ch));
        return [null, both];
      }
      return [null, theEnd(true)];
    }
    const blank = () => side("");
    const fill = (pageEl, node) => pageEl.replaceChildren(...(node ? [node] : []));

    function paint() {
      const [l, r] = sides(at);
      const focused = document.activeElement;
      fill(left, l);
      fill(right, r);
      book.classList.toggle("closed", at === 0);
      prevBtn.disabled = at === 0;
      nextBtn.disabled = at === pages - 1;
      // a button that just became disabled would drop the keyboard focus to the page: hand it to the other one
      if (focused === nextBtn && nextBtn.disabled) prevBtn.focus({ preventScroll: true });
      if (focused === prevBtn && prevBtn.disabled) nextBtn.focus({ preventScroll: true });
      nextBtn.textContent = at === 0 ? "Open the book" : at === pages - 1 ? "The end" : "Next page";
      count.textContent = at === 0 ? "" : `${at + 1} / ${pages}`;
    }

    async function turn(dir) {
      const to = at + dir;
      if (turning || to < 0 || to >= pages) return;
      turning = true;
      try {
        if (!kit.reduced) {
          const [curL, curR] = sides(at), [newL, newR] = sides(to);
          const leaf = kit.el("div", `leaf ${dir > 0 ? "fwd" : "back"}`);
          const front = kit.el("div", "face front"), back = kit.el("div", "face back");
          let frames;
          if (wide.matches) {
            if (dir > 0) {
              front.appendChild(curR);
              back.appendChild(newL || blank());
              fill(right, newR);
            } else {
              front.appendChild(curL || blank());
              back.appendChild(newR);
              fill(left, newL);
            }
            book.classList.toggle("closed", to === 0);
            frames = dir > 0 ? ["rotateY(0deg)", "rotateY(-180deg)"] : ["rotateY(0deg)", "rotateY(180deg)"];
          } else if (dir > 0) {
            front.appendChild(curR);
            back.appendChild(blank());
            fill(right, newR);
            frames = ["rotateY(0deg)", "rotateY(-180deg)"];
          } else {
            front.appendChild(newR);
            back.appendChild(blank());
            frames = ["rotateY(-180deg)", "rotateY(0deg)"];
          }
          leaf.append(front, back);
          book.appendChild(leaf);
          await leaf.animate(frames.map((transform) => ({ transform })), { duration: 900, easing: "cubic-bezier(0.45, 0, 0.2, 1)" }).finished;
          leaf.remove();
        }
        at = to;
        paint();
        kit.announce(at === pages - 1 ? `The end. Page ${at + 1} of ${pages}.` : `Page ${at + 1} of ${pages}.`);
      } finally {
        turning = false;
      }
    }

    nextBtn.addEventListener("click", () => turn(1));
    prevBtn.addEventListener("click", () => turn(-1));
    // a tap on the right page turns forward, on the left page back (but not on a photo print)
    right.addEventListener("click", (e) => !e.target.closest("button") && turn(1));
    left.addEventListener("click", (e) => !e.target.closest("button") && turn(-1));
    kit.swipe(book, (dir) => turn(dir));
    addEventListener("keydown", (e) => {
      if (document.querySelector("dialog[open]") || e.altKey || e.metaKey || e.ctrlKey) return;
      if (e.key === "ArrowRight" || e.key === "PageDown") turn(1);
      if (e.key === "ArrowLeft" || e.key === "PageUp") turn(-1);
    });
    wide.addEventListener("change", () => !turning && paint());

    paint();
    const first = right.querySelector("img");
    return { ready: first ? kit.ready(first) : null, settle: () => nextBtn.focus({ preventScroll: true }) };
  }
};
