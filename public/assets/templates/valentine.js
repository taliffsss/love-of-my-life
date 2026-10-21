// Template "valentine": loves me, loves me not. Seven petals; each one plucked says the next words. The seventh says "Loves me"
// (there is no other way it can end), hearts burst, and the photos (each opening the lightbox) and the letter open.
(window.LoveTemplates = window.LoveTemplates || {}).valentine = {
  build(kit, content) {
    const byId = (id) => document.getElementById(id);
    const root = byId("vl"), say = byId("vlSay"), hint = byId("vlHint"), opened = byId("vlOpen"), flower = byId("vlFlower");
    const petals = Array.from(root.querySelectorAll(".vl-petal"));
    const photos = content.photos || [], recipient = content.recipient || "";

    const sheet = kit.letterSheet(content, "With all my love, for");
    sheet.tabIndex = -1;
    byId("vlLetter").appendChild(sheet);
    if (photos.length) byId("vlPhotos").appendChild(kit.prints(photos, recipient));
    else byId("vlPhotos").remove();

    let plucked = 0, busy = false;
    async function pluck(petal) {
      if (busy || petal.classList.contains("gone")) return;
      busy = true;
      plucked++;
      const loves = plucked % 2 === 1;
      petal.classList.add("gone");
      petal.disabled = true;
      say.textContent = loves ? "Loves me…" : "Loves me not…";
      say.classList.toggle("yes", loves);
      hint.textContent = plucked < petals.length ? "" : hint.textContent;
      kit.announce(`${plucked} of ${petals.length}: ${say.textContent}`);
      if (plucked < petals.length) {
        await kit.wait(kit.reduced ? 0 : 380);
        busy = false;
        return;
      }
      hint.textContent = "";
      say.textContent = "Loves me. Always.";
      root.classList.add("done");
      kit.celebrate(flower, 30);
      await kit.wait(kit.reduced ? 0 : 1800);
      root.classList.add("open");
      opened.hidden = false;
      root.scrollTop = 0;
      sheet.focus({ preventScroll: true });
    }
    petals.forEach((petal) => petal.addEventListener("click", () => pluck(petal)));

    return { settle: () => petals[0].focus({ preventScroll: true }) };
  }
};
