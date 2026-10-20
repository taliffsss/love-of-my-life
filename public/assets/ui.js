// The product's small helpers: status toast, copy buttons, dialogs, and the page editor (photos, design preview,
// subdomain availability). Everything works without it except photo uploads, which need the browser to resize photos.
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";
const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

// ---------- status toast ----------
const toast = $("#toast");
if (toast) setTimeout(() => toast.classList.add("hide"), 5000);

// ---------- copy ----------
for (const btn of $$("[data-copy]")) {
  btn.addEventListener("click", async () => {
    const input = $(btn.dataset.copy);
    if (!input) return;
    try {
      await navigator.clipboard.writeText(input.value);
    } catch {
      input.select();
      document.execCommand?.("copy");
    }
    const label = btn.textContent;
    btn.textContent = "Copied";
    setTimeout(() => (btn.textContent = label), 1600);
  });
}

// ---------- landing header: transparent over the dark hero, dark text once a light section is under it ----------
{
  const nav = $(".hero-nav");
  const hero = $(".hero");
  if (nav && hero) {
    const sync = () => nav.classList.toggle("on-light", hero.getBoundingClientRect().bottom <= nav.offsetHeight / 2);
    addEventListener("scroll", sync, { passive: true });
    addEventListener("resize", sync);
    sync();
  }
}

// ---------- dialogs ----------
for (const opener of $$("[data-open]")) {
  const dialog = $(opener.dataset.open);
  if (!dialog?.showModal) continue;
  opener.addEventListener("click", () => {
    dialog.showModal();
    dialog.querySelector("input")?.focus();
  });
  for (const closer of $$("[data-close]", dialog)) closer.addEventListener("click", () => dialog.close());
}

// ---------- editor ----------
const editor = $("main[data-page]");
if (editor) {
  const slug = editor.dataset.page;
  const maxBytes = Number(editor.dataset.maxBytes) || 10 * 1024 * 1024;
  const base = `/dashboard/pages/${slug}`;
  const frame = $("#preview-frame");
  const status = $("#photo-status");
  const thumbs = $("#thumbs");
  const zone = $("#upload-zone");
  const input = $("#photo-input");

  const say = (text) => {
    if (status) status.textContent = text;
  };
  const reloadPreview = (query = "") => {
    if (frame) frame.src = `${base}/preview${query}`;
  };
  async function send(method, url, body) {
    const isForm = body instanceof FormData;
    const res = await fetch(url, {
      method,
      credentials: "same-origin",
      headers: { "X-CSRF-TOKEN": csrf, Accept: "application/json", ...(isForm || !body ? {} : { "Content-Type": "application/json" }) },
      body: isForm ? body : body ? JSON.stringify(body) : undefined
    });
    const data = await res.json().catch(() => ({}));
    if (res.status === 419) throw new Error("Your session expired. Reload the page and try again.");
    if (res.status === 413) throw new Error(`This photo is too large. Photos can be up to ${mb(maxBytes)} each.`);
    if (!res.ok) throw Object.assign(new Error(data.message || `Something went wrong (HTTP ${res.status}).`), { code: data.code, usage: data.usage });
    return data;
  }

  // sizes as phones and computers show them (1 MB = 1024 × 1024 bytes), like the server's messages
  const mb = (bytes) => `${String(Math.round((bytes / 1048576) * 10) / 10)} MB`;

  // The account's photos (partials/photo-usage): numbers, meter and note after a photo is added or deleted.
  const usageBox = $("#photo-usage");
  const remaining = () => Number(usageBox?.dataset.remaining ?? Infinity);
  function showUsage(u) {
    if (!usageBox || !u) return;
    usageBox.dataset.remaining = String(u.remaining);
    $("#usage-used", usageBox).textContent = String(u.used);
    $("#usage-total", usageBox).textContent = String(u.total);
    $("#usage-left", usageBox).textContent = u.over > 0 ? `${u.over} over` : `${u.remaining} left`;
    const bar = $(".meter", usageBox);
    bar?.setAttribute("aria-valuenow", String(u.used));
    bar?.setAttribute("aria-valuemax", String(u.total));
    const fill = $("#usage-meter", usageBox);
    if (fill) fill.style.width = `${u.total > 0 ? Math.min(100, Math.round((u.used / u.total) * 100)) : 100}%`;
    const state = u.over > 0 ? "over" : u.remaining === 0 ? "full" : u.remaining <= 3 ? "low" : "ok";
    usageBox.classList.remove("usage-ok", "usage-low", "usage-full", "usage-over");
    usageBox.classList.add(`usage-${state}`);
    // the same notes as partials/photo-usage
    const notes = {
      over: "A plan ended, so your account holds more photos than it now includes. Nothing was removed; to add new ones, delete some, buy more or upgrade a plan.",
      full: `You have used every photo. Buy ${usageBox.dataset.pack}, upgrade your plan, or delete one to make room.`,
      low: `Only ${u.remaining} left. When they are used up, add ${usageBox.dataset.pack} or upgrade your plan.`,
    };
    const alert = $("#usage-alert", usageBox);
    if (alert) {
      alert.className = `notice ${state === "low" ? "notice-brand" : "notice-warn"}`;
      alert.textContent = notes[state] || "";
      alert.hidden = state === "ok";
    }
    if (state !== "ok") usageBox.querySelector("details")?.setAttribute("open", "");
    if (zone) zone.hidden = u.remaining <= 0;
  }

  // Photos are resized in the browser (longest side 2200 px, JPEG) so phone photos upload quickly. The photo keeps its
  // proportions and the way the camera held it; transparent parts become white rather than JPEG's black.
  async function shrink(file) {
    const bmp = await createImageBitmap(file, { imageOrientation: "from-image" });
    const k = Math.min(1, 2200 / Math.max(bmp.width, bmp.height));
    const c = document.createElement("canvas");
    c.width = Math.max(1, Math.round(bmp.width * k));
    c.height = Math.max(1, Math.round(bmp.height * k));
    const ctx = c.getContext("2d");
    ctx.fillStyle = "#fff";
    ctx.fillRect(0, 0, c.width, c.height);
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = "high";
    ctx.drawImage(bmp, 0, 0, c.width, c.height);
    bmp.close?.();
    return new Promise((res, rej) => c.toBlob((b) => (b ? res(b) : rej(new Error("That photo could not be read."))), "image/jpeg", 0.9));
  }

  // Photos need a plan first: without one, asking to add photos opens the "subscribe first" dialog instead.
  const subscribed = () => editor.dataset.subscribed !== "0";
  const subscribeDialog = $("#subscribe-dialog");
  if (subscribeDialog) for (const closer of $$("[data-close]", subscribeDialog)) closer.addEventListener("click", () => subscribeDialog.close());
  function askToSubscribe() {
    if (input) input.value = "";
    if (subscribeDialog?.showModal) subscribeDialog.showModal();
    else say("Subscribe to a plan first, then you can add photos. Choose one under Plan.");
  }

  async function upload(files) {
    if (!files.length) return;
    if (!subscribed()) return askToSubscribe();
    // the server checks all of this again; checking first saves uploading what it would refuse
    const tooBig = files.filter((f) => f.size > maxBytes);
    let queue = files.filter((f) => f.size <= maxBytes);
    const notes = tooBig.map((f) => `${f.name} is ${mb(f.size)}. Photos can be up to ${mb(maxBytes)} each.`);
    const room = remaining();
    if (queue.length > room) {
      const left = queue.length - room;
      queue = queue.slice(0, Math.max(0, room));
      notes.push(`${left} photo${left === 1 ? "" : "s"} did not fit: ${room > 0 ? `there was room for ${room} more` : "your account has no photos left"}. Add ${usageBox?.dataset.pack || "more photos"} below, or upgrade your plan.`);
    }
    if (!queue.length) {
      say(notes.join(" "));
      if (input) input.value = "";
      if (room <= 0) usageBox?.querySelector("details")?.setAttribute("open", "");
      return;
    }
    let done = 0;
    zone?.setAttribute("aria-busy", "true");
    try {
      for (const [i, file] of queue.entries()) {
        say(`Uploading ${i + 1} of ${queue.length}…`);
        let blob;
        try {
          blob = await shrink(file);
        } catch {
          blob = file; // let the server judge it
        }
        if (blob.size > maxBytes) throw new Error(`${file.name} is ${mb(blob.size)}. Photos can be up to ${mb(maxBytes)} each.`);
        const fd = new FormData();
        fd.append("photo", blob, file.name.replace(/\.[^.]+$/, "") + ".jpg");
        const data = await send("POST", `${base}/photos`, fd);
        showUsage(data.usage);
        done++;
      }
      say([`Added ${done} photo${done === 1 ? "" : "s"}.`, ...notes].join(" "));
      if (!notes.length) {
        location.hash = "photos";
        location.reload();
      } else {
        setTimeout(() => location.reload(), 4000);
      }
    } catch (e) {
      if (e.code === "subscription_required") {
        editor.dataset.subscribed = "0";
        askToSubscribe();
      }
      if (e.code === "photo_limit") {
        showUsage(e.usage);
        usageBox?.querySelector("details")?.setAttribute("open", "");
      }
      say([done ? `Added ${done}, then: ${e.message}` : e.message, ...notes].join(" "));
      if (done) setTimeout(() => location.reload(), 4000);
    } finally {
      zone?.removeAttribute("aria-busy");
      if (input) input.value = "";
    }
  }
  input?.addEventListener("change", () => upload(Array.from(input.files || [])));
  // the picker itself must not open for an account without a plan
  zone?.addEventListener("click", (e) => {
    if (subscribed()) return;
    e.preventDefault();
    askToSubscribe();
  });
  if (zone) {
    zone.addEventListener("dragover", (e) => {
      e.preventDefault();
      zone.classList.add("drag");
    });
    zone.addEventListener("dragleave", () => zone.classList.remove("drag"));
    zone.addEventListener("drop", (e) => {
      e.preventDefault();
      zone.classList.remove("drag");
      upload(Array.from(e.dataTransfer?.files || []).filter((f) => f.type.startsWith("image/")));
    });
  }

  function renumber() {
    $$(".thumb", thumbs).forEach((t, i) => {
      $(".n", t).textContent = String(i + 1);
      $("img", t).alt = `Photo ${i + 1}`;
      $('[data-move="-1"]', t).setAttribute("aria-label", `Move photo ${i + 1} earlier`);
      $('[data-move="1"]', t).setAttribute("aria-label", `Move photo ${i + 1} later`);
      $("[data-delete]", t).setAttribute("aria-label", `Delete photo ${i + 1}`);
    });
  }
  thumbs?.addEventListener("click", async (e) => {
    const btn = e.target.closest("button");
    const thumb = btn?.closest(".thumb");
    if (!btn || !thumb) return;
    if (btn.hasAttribute("data-delete")) {
      if (!confirm("Delete this photo?")) return;
      btn.disabled = true;
      try {
        const data = await send("DELETE", `${base}/photos/${thumb.dataset.id}`);
        thumb.remove();
        renumber();
        const count = $("#photo-count");
        if (count) count.textContent = `${data.count} on this page`;
        showUsage(data.usage);
        say("Photo deleted.");
        reloadPreview();
      } catch (problem) {
        btn.disabled = false;
        say(problem.message);
      }
      return;
    }
    const step = Number(btn.dataset.move || 0);
    if (!step) return;
    const all = $$(".thumb", thumbs);
    const at = all.indexOf(thumb);
    const to = at + step;
    if (to < 0 || to >= all.length) return;
    if (step < 0) thumbs.insertBefore(thumb, all[to]);
    else thumbs.insertBefore(thumb, all[to].nextSibling);
    renumber();
    btn.focus();
    try {
      await send("POST", `${base}/photos/order`, { order: $$(".thumb", thumbs).map((t) => Number(t.dataset.id)) });
      say("Order saved.");
      reloadPreview();
    } catch (problem) {
      say(`${problem.message} Reloading…`);
      setTimeout(() => location.reload(), 1500);
    }
  });

  // Design: preview a template or colour before saving it. A template the page's plan does not include can be
  // previewed, but not saved (the server refuses it too): say which plan has it.
  const designForm = $("#design-form");
  if (designForm) {
    const link = $("#design-preview-link");
    const locked = $("#design-locked");
    const save = $("#design-save");
    const checkLocked = () => {
      const note = designForm.querySelector('input[name="layout"]:checked')?.dataset.locked || "";
      if (locked) {
        locked.textContent = note;
        locked.hidden = note === "";
      }
      if (save) save.disabled = note !== "";
    };
    // the ten designs shown are those of the chosen occasion; the chosen template carries over to them
    const grids = $$(".design-grid", designForm);
    const occasionOf = () => designForm.querySelector('input[name="occasion"]:checked')?.value ?? "";
    const showGrid = (carry) => {
      const occasion = occasionOf();
      const previous = designForm.querySelector('input[name="layout"]:checked')?.value;
      for (const g of grids) g.hidden = g.dataset.occasion !== occasion;
      const next = grids.find((g) => g.dataset.occasion === occasion);
      if (carry && next && previous) {
        const radio = $$('input[name="layout"]', next).find((r) => r.value === previous);
        if (radio) radio.checked = true;
      }
    };
    if (grids.length) {
      document.documentElement.classList.add("js");
      showGrid(false);
    }
    designForm.addEventListener("change", (event) => {
      if (event.target.name === "occasion") showGrid(true);
      const fd = new FormData(designForm);
      const query = `?layout=${encodeURIComponent(fd.get("layout") || "")}&theme=${encodeURIComponent(fd.get("theme") || "")}&occasion=${encodeURIComponent(fd.get("occasion") || "")}`;
      reloadPreview(query);
      if (link) link.href = `${base}/preview${query}`;
      checkLocked();
    });
    checkLocked();
  }

  // Subdomain: say whether a name is free while it is typed.
  const sub = $("#subdomain");
  const help = $("#subdomain-help");
  if (sub && help) {
    const initial = sub.value;
    let t = 0, seq = 0;
    sub.addEventListener("input", () => {
      clearTimeout(t);
      const name = sub.value.trim().toLowerCase();
      if (!name || name === initial) {
        help.textContent = "3 to 30 lowercase letters, numbers or dashes. It works as soon as you save it.";
        return;
      }
      t = setTimeout(async () => {
        const mine = ++seq;
        try {
          const res = await fetch(`/dashboard/subdomains/check?name=${encodeURIComponent(name)}`, { headers: { Accept: "application/json" }, credentials: "same-origin" });
          const data = await res.json();
          if (mine !== seq) return;
          help.textContent = data.message;
          sub.setAttribute("aria-invalid", data.available ? "false" : "true");
        } catch {
          /* the server check on save still applies */
        }
      }, 350);
    });
  }
}

// ---------- occasion templates: show the ten templates of the chosen occasion ----------
{
  const tabs = $$('input[name="occasion"]');
  const grids = $$(".template-grid");
  if (tabs.length && grids.length) {
    document.documentElement.classList.add("js");
    const show = () => {
      const on = tabs.find((t) => t.checked)?.value;
      for (const g of grids) g.classList.toggle("on", g.dataset.occasion === on);
    };
    for (const t of tabs) t.addEventListener("change", show);
    show();
  }
}

// ---------- admin users: the row's icons act in a dialog (without script they open the user's own page) ----------
{
  const links = $$("[data-user-action]");
  const statusDialog = $("#user-status-dialog");
  const deleteDialog = $("#user-delete-dialog");
  if (links.length && statusDialog?.showModal && deleteDialog?.showModal) {
    const status = {
      activate: { title: "Reactivate", text: "can sign in again.", value: "active", go: "Reactivate", reason: false },
      deactivate: { title: "Deactivate", text: "keeps everything but can no longer sign in, and is signed out now.", value: "deactivated", go: "Deactivate", reason: false },
      suspend: { title: "Suspend", text: "keeps everything but can no longer sign in, and is signed out now.", value: "suspended", go: "Suspend", reason: true },
    };
    for (const link of links) {
      link.addEventListener("click", (event) => {
        event.preventDefault();
        const { userAction: action, userId: id, userName: name } = link.dataset;
        if (action === "delete") {
          deleteDialog.querySelector("form").action = `/admin/users/${id}`;
          $("#user-delete-title").textContent = `Delete ${name}?`;
          deleteDialog.showModal();
          $("#confirm-user-delete").focus();
          return;
        }
        const copy = status[action];
        statusDialog.querySelector("form").action = `/admin/users/${id}/status`;
        statusDialog.querySelector('input[name="status"]').value = copy.value;
        $("#user-status-title").textContent = `${copy.title} ${name}?`;
        $("#user-status-text").textContent = `${name} ${copy.text}`;
        $("#user-status-go").textContent = copy.go;
        $("#user-status-reason").hidden = !copy.reason;
        statusDialog.showModal();
        (copy.reason ? $("#reason") : $("#user-status-go")).focus();
      });
    }
    for (const dialog of [statusDialog, deleteDialog]) for (const closer of $$("[data-close]", dialog)) closer.addEventListener("click", () => dialog.close());
  }
}

// ---------- sign-in / sign-up: the left panel ----------
// A sample page for each occasion: tabs (or the arrow keys) switch it, it also rotates by itself until someone picks one,
// the letter types itself out, the card tilts towards the mouse and a click makes hearts. All of it is decoration: without
// script, or with reduced motion, the first sample is simply shown.
{
  const art = $("[data-auth-art]");
  if (art) {
    const reduce = matchMedia("(prefers-reduced-motion: reduce)").matches;
    const tabs = $$(".art-tab", art);
    const samples = $$(".art-sample", art);
    const glows = $$(".art-glow", art);
    const card = $("[data-art-card]", art);
    const lettie = $("[data-lettie]", art);
    const bubble = $("[data-bubble]", art);
    let index = 0;
    let typer = 0;
    let rotate = 0;
    let picked = false;
    const visible = () => art.offsetParent !== null && !document.hidden;

    const type = (el) => {
      clearInterval(typer);
      const text = el.dataset.text || el.textContent;
      if (reduce) return (el.textContent = text);
      el.textContent = "";
      el.classList.add("typing");
      lettie?.classList.add("talking");
      let i = 0;
      typer = setInterval(() => {
        i += 2;
        el.textContent = text.slice(0, i);
        if (i >= text.length) {
          clearInterval(typer);
          el.classList.remove("typing");
          lettie?.classList.remove("talking");
        }
      }, 28);
    };

    const show = (next, focus = false) => {
      index = (next + samples.length) % samples.length;
      const key = samples[index].dataset.sample;
      art.dataset.active = key;
      samples.forEach((s, i) => {
        s.hidden = i !== index;
        s.classList.toggle("on", i === index);
      });
      tabs.forEach((t, i) => {
        t.setAttribute("aria-selected", String(i === index));
        t.tabIndex = i === index ? 0 : -1;
      });
      glows.forEach((g) => g.classList.toggle("on", g.dataset.for === key));
      if (bubble) {
        bubble.textContent = samples[index].dataset.says || "";
        bubble.style.animation = "none";
        void bubble.offsetWidth; // restart the pop-in
        bubble.style.animation = "";
      }
      type($(".art-letter", samples[index]));
      if (focus) tabs[index].focus();
    };

    tabs.forEach((tab, i) => {
      tab.addEventListener("click", () => {
        picked = true;
        show(i);
      });
      tab.addEventListener("keydown", (event) => {
        if (event.key !== "ArrowRight" && event.key !== "ArrowLeft") return;
        event.preventDefault();
        picked = true;
        show(index + (event.key === "ArrowRight" ? 1 : -1), true);
      });
    });

    if (!reduce) {
      rotate = setInterval(() => {
        if (!picked && visible()) show(index + 1);
      }, 8000);
      type($(".art-letter", samples[0]));

      // tilt towards the pointer
      art.addEventListener("pointermove", (event) => {
        if (!card) return;
        const box = card.getBoundingClientRect();
        const x = (event.clientX - (box.left + box.width / 2)) / (box.width / 2);
        const y = (event.clientY - (box.top + box.height / 2)) / (box.height / 2);
        card.classList.add("moving");
        card.style.setProperty("--rx", `${Math.max(-1, Math.min(1, x)) * 7}deg`);
        card.style.setProperty("--ry", `${Math.max(-1, Math.min(1, y)) * -6}deg`);
      });
      art.addEventListener("pointerleave", () => {
        card?.classList.remove("moving");
        card?.style.setProperty("--rx", "0deg");
        card?.style.setProperty("--ry", "0deg");
      });

      // Lettie waves and hugs a heart when clicked
      $("[data-lettie-btn]", art)?.addEventListener("click", () => {
        lettie.classList.add("love");
        document.getElementById("l-wave")?.beginElement?.();
        setTimeout(() => lettie.classList.remove("love"), 1600);
      });

      // hearts where the panel is clicked (not on the tabs)
      art.addEventListener("click", (event) => {
        if (event.target.closest(".art-tab, a")) return;
        const box = art.getBoundingClientRect();
        for (let n = 0; n < 7; n++) {
          const heart = document.createElement("span");
          heart.className = "art-burst";
          heart.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-8-4.9-8-11.1C4 6.7 6.3 5 8.6 5c1.5 0 2.9.8 3.4 2 .5-1.2 1.9-2 3.4-2C17.7 5 20 6.7 20 9.9 20 16.1 12 21 12 21z"/></svg>';
          heart.style.left = `${event.clientX - box.left}px`;
          heart.style.top = `${event.clientY - box.top}px`;
          heart.style.setProperty("--dx", `${(Math.random() - 0.5) * 160}px`);
          heart.style.setProperty("--dy", `${-40 - Math.random() * 130}px`);
          heart.style.setProperty("--r", `${(Math.random() - 0.5) * 80}deg`);
          art.append(heart);
          setTimeout(() => heart.remove(), 1200);
        }
      });
    }
  }
}


// ---------- forms that ask first (data-confirm) ----------
for (const form of $$("form[data-confirm]")) {
  form.addEventListener("submit", (event) => {
    if (!confirm(form.dataset.confirm)) event.preventDefault();
  });
}

// ---------- paying: wait for the payment, and send a card straight to the provider ----------
{
  // After starting a payment (or coming back from the wallet or the bank's page): follow the wallet link once, then ask
  // whether it was paid until it is, and show the choices again when the payment has run out.
  const wait = $("#order-wait");
  if (wait) {
    const state = $("#wait-state", wait);
    const expires = Number(wait.dataset.expires) * 1000;
    let busy = false;
    const check = async () => {
      if (busy) return;
      busy = true;
      try {
        const res = await fetch(wait.dataset.statusUrl, { headers: { Accept: "application/json" }, credentials: "same-origin" });
        const data = await res.json();
        if (data.paid) {
          if (state) state.textContent = "Payment received. Thank you!";
          setTimeout(() => location.reload(), 600);
        }
      } catch {
        // a missed check is retried on the next one
      } finally {
        busy = false;
      }
    };
    const poll = setInterval(check, 5000);
    const expiry = setTimeout(() => location.reload(), Math.max(1000, expires - Date.now()));
    window.addEventListener("pagehide", () => {
      clearInterval(poll);
      clearTimeout(expiry);
    });
    const link = $("#continue-link", wait);
    if (link && wait.dataset.go === "1") setTimeout(() => (location.href = link.href), 700);
    check();
  }

  // Card: the number is read here and sent to PayMongo with the public key; our server only starts the payment and says
  // whether it was paid. 3-D Secure (the bank's approval page) is followed when PayMongo asks for it.
  for (const form of $$("[data-card-form]")) {
    const box = (name) => form.querySelector(`[data-cc="${name}"]`);
    const errorBox = $("[data-cc-error]", form);
    const submit = $("[data-cc-submit]", form);
    const fail = (message) => {
      if (errorBox) {
        errorBox.textContent = message;
        errorBox.hidden = false;
      }
      if (submit) submit.disabled = false;
    };
    const luhn = (digits) => {
      let sum = 0;
      for (let i = 0; i < digits.length; i++) {
        let d = Number(digits[digits.length - 1 - i]);
        if (i % 2 === 1) {
          d *= 2;
          if (d > 9) d -= 9;
        }
        sum += d;
      }
      return sum % 10 === 0;
    };
    box("number")?.addEventListener("input", (event) => {
      const digits = event.target.value.replace(/\D/g, "").slice(0, 19);
      event.target.value = digits.replace(/(.{4})/g, "$1 ").trim();
    });
    box("exp")?.addEventListener("input", (event) => {
      const digits = event.target.value.replace(/\D/g, "").slice(0, 4);
      event.target.value = digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
    });
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (errorBox) errorBox.hidden = true;
      const number = box("number").value.replace(/\D/g, "");
      const [mm, yy] = box("exp").value.split("/");
      const month = Number(mm);
      const year = Number(yy) < 100 ? 2000 + Number(yy) : Number(yy);
      const cvc = box("cvc").value.replace(/\D/g, "");
      const name = box("name").value.trim();
      const now = new Date();
      if (!name) return fail("Enter the name on the card.");
      if (number.length < 13 || !luhn(number)) return fail("Check the card number.");
      if (!(month >= 1 && month <= 12) || !year || year < now.getFullYear() || (year === now.getFullYear() && month < now.getMonth() + 1)) return fail("Check the expiry date.");
      if (cvc.length < 3) return fail("Check the security code (CVC).");
      if (submit) submit.disabled = true;
      try {
        const started = await fetch(form.dataset.cardUrl, { method: "POST", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf }, credentials: "same-origin" });
        const begun = await started.json();
        if (!started.ok) return fail(begun.message || "We could not start the payment. Please try again.");
        const auth = { Authorization: `Basic ${btoa(`${begun.public_key}:`)}`, "Content-Type": "application/json", Accept: "application/json" };
        const made = await fetch("https://api.paymongo.com/v1/payment_methods", {
          method: "POST",
          headers: auth,
          body: JSON.stringify({ data: { attributes: { type: "card", details: { card_number: number, exp_month: month, exp_year: year, cvc }, billing: { name, email: begun.email } } } }),
        });
        const method = await made.json();
        if (!made.ok) return fail(method.errors?.[0]?.detail || "The card was not accepted. Check the details.");
        const attached = await fetch(`https://api.paymongo.com/v1/payment_intents/${encodeURIComponent(begun.intent)}/attach`, {
          method: "POST",
          headers: auth,
          body: JSON.stringify({ data: { attributes: { payment_method: method.data.id, client_key: begun.client_key, return_url: begun.return_url } } }),
        });
        const result = await attached.json();
        if (!attached.ok) return fail(result.errors?.[0]?.detail || "The payment was not accepted. Please try again.");
        const attributes = result.data.attributes;
        if (attributes.status === "awaiting_next_action" && attributes.next_action?.redirect?.url) {
          location.href = attributes.next_action.redirect.url; // the bank's approval page (3-D Secure)
        } else if (attributes.status === "awaiting_payment_method") {
          fail(attributes.last_payment_error?.failed_message || "The card was declined. Try another card.");
        } else {
          location.href = begun.return_url; // paid, or being processed: the order page confirms it
        }
      } catch {
        fail("We could not reach the payment provider. Please try again.");
      }
    });
  }
}
