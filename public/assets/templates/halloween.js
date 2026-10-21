// Template "halloween": knock three times on the haunted door. Each knock shakes the door and the pumpkin flickers; the third
// opens it, and the treats (the photos, each opening the lightbox) and the letter are inside.
(window.LoveTemplates = window.LoveTemplates || {}).halloween = {
  build(kit, content) {
    const byId = (id) => document.getElementById(id);
    const root = byId("hw"), door = byId("hwDoor"), hint = byId("hwHint"), opened = byId("hwOpen");
    const dots = Array.from(root.querySelectorAll(".hw-knocks i"));
    const photos = content.photos || [], recipient = content.recipient || "";
    const WORDS = ["Knock again…", "One more time…"];

    const sheet = kit.letterSheet(content, "A treat for");
    sheet.tabIndex = -1;
    byId("hwLetter").appendChild(sheet);
    if (photos.length) byId("hwTreats").appendChild(kit.prints(photos, recipient));
    else byId("hwTreats").remove();

    let knocks = 0, busy = false;
    async function knock() {
      if (busy || knocks >= 3) return;
      busy = true;
      dots[knocks].classList.add("on");
      knocks++;
      root.classList.remove("knock");
      void root.offsetWidth;
      root.classList.add("knock");
      kit.announce(knocks < 3 ? `Knock ${knocks} of 3.` : "The door creaks open.");
      if (knocks < 3) {
        hint.textContent = WORDS[knocks - 1];
        await kit.wait(kit.reduced ? 0 : 420);
        busy = false;
        return;
      }
      hint.textContent = "";
      root.classList.add("opening");
      await kit.wait(kit.reduced ? 0 : 1500);
      root.classList.add("open");
      opened.hidden = false;
      root.scrollTop = 0;
      kit.announce(`The door is open: ${photos.length ? photos.length + " photos and " : ""}a letter for ${recipient || "you"}.`);
      sheet.focus({ preventScroll: true });
    }
    door.addEventListener("click", knock);

    return { settle: () => door.focus({ preventScroll: true }) };
  }
};
