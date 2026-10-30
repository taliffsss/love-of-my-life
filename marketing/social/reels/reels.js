// Five short vertical videos for the Facebook Page (also fine for Reels and TikTok), narrated by Lettie.
//
// Same idea as the promo (marketing/promo/promo.js): a deterministic timeline. reel.seek(t) draws the frame at t seconds
// and resolves once its image is decoded, so render_reels.py can screenshot frame after frame and always get the same
// video. Nothing moves by itself: no CSS animations, no timers. reels.html?v=<id>, 1080x1920, 30 fps.
//
// The phone plays real clips of the product (marketing/promo/shots.py filmed /demo in slow motion). The words are in
// VIDEOS below, Lettie's lines are in the same place (voice_reels.py speaks them), and render_reels.py reads reel.cues
// (sound effects), reel.groove (when the drums play) and reel.audit().
(() => {
  const params = new URLSearchParams(location.search);
  const ID = params.get("v") || "ofw-unlock";
  const SHOTS = "/marketing/out/promo/shots/";
  const QR = "/marketing/out/promo/qr.svg";
  const URL_TEXT = "loveofmylife.love";
  const W = 1080, H = 1920, FPS = 30;
  const HEART = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-8-4.9-8-11.1C4 6.7 6.3 5 8.6 5c1.5 0 2.9.8 3.4 2 .5-1.2 1.9-2 3.4-2C17.7 5 20 6.7 20 9.9 20 16.1 12 21 12 21z"/></svg>';

  // when a clip frame plays, in video time
  const at = (start, frame, rate = 1, from = 0) => +(start + (frame - from) / (FPS * rate)).toFixed(3);
  const END_LINE = "Love of My Life. Build it free.";

  const VIDEOS = {
    // 1. For OFWs and long-distance couples
    "ofw-unlock": {
      title: "Miles apart, one link away", duration: 15.8, theme: "t-navy", groove: [2.6, 12.4], endAt: 12.4,
      kicker: [["For OFWs and long distance", 0.5, 4.7]],
      heads: [["Miles apart.<br>One link away.", 0.7, 4.7], ["Only they can<br>open it.", 4.9, 9.0], ["Whenever they<br>miss you.", 9.2, 12.2]],
      phone: { at: 4.6, until: 12.2, clips: [{ name: "lock", at: 4.6, from: 0, rate: 1 }, { name: "envelope", at: 9.2, from: 0, rate: 1.15 }] },
      lettie: { keys: [[0, 540, 1010, 560], [4.2, 160, 1400, 250]], waving: [[0.9, 3.4]] },
      cues: [{ t: 4.6, kind: "whoosh", gain: 0.6 }, { t: at(4.6, 40), kind: "tap" }, { t: at(4.6, 76), kind: "sparkle" }, { t: 9.2, kind: "whoosh", gain: 0.5 }, { t: at(9.2, 8, 1.15), kind: "pop" }, { t: at(9.2, 22, 1.15), kind: "paper" }],
      lines: [["Miles apart? You're still one link away.", 0.9, 4.5], ["Lock a letter with a question only they can answer.", 4.8, 9.0], ["They open it whenever they miss you.", 9.3, 12.2], [END_LINE, 12.7, 15.5]],
    },
    // 2. Asking someone out (couples, 16+)
    "scratch-ask": {
      title: "Scratch to find the question", duration: 15.4, theme: "", groove: [2.6, 12.2], endAt: 12.2,
      kicker: [["Ask them out", 0.5, 4.5]],
      heads: [["Want to ask<br>someone out?", 0.7, 4.5], ["Scratch to find<br>the question.", 4.7, 9.4], ["They scratch.<br>They smile.", 9.6, 12.0]],
      phone: { at: 4.5, until: 12.0, clips: [{ name: "scratch", at: 4.5, from: 2, rate: 0.8 }] },
      lettie: { keys: [[0, 540, 1010, 560], [4.2, 160, 1400, 250]], waving: [[0.9, 3.4]] },
      cues: [{ t: 4.5, kind: "whoosh", gain: 0.6 }, { t: at(4.5, 40, 0.8, 2), kind: "tap" }, { t: at(4.5, 76, 0.8, 2), kind: "sparkle" }],
      lines: [["Want to ask someone out?", 0.9, 4.4], ["Hide the question under a scratch card.", 4.8, 9.2], ["They scratch, they smile, they answer.", 9.5, 12.0], [END_LINE, 12.5, 15.1]],
    },
    // 3. Monthsary
    "monthsary-book": {
      title: "Monthsary tomorrow?", duration: 15.4, theme: "t-gold", groove: [2.6, 12.2], endAt: 12.2,
      kicker: [["Monthsary", 0.5, 4.5]],
      heads: [["Monthsary<br>tomorrow?", 0.7, 4.5], ["Skip the text.<br>Say it properly.", 4.7, 8.5], ["A little book<br>of you two.", 8.7, 12.0]],
      phone: { at: 4.5, until: 12.0, clips: [{ name: "storybook", at: 4.5, from: 0, rate: 0.75 }] },
      lettie: { keys: [[0, 540, 1010, 560], [4.2, 160, 1400, 250]], waving: [[0.9, 3.4]] },
      cues: [{ t: 4.5, kind: "whoosh", gain: 0.6 }, { t: at(4.5, 8, 0.75), kind: "page" }, { t: at(4.5, 46, 0.75), kind: "page" }, { t: at(4.5, 84, 0.75), kind: "page" }],
      lines: [["Monthsary tomorrow?", 0.9, 4.4], ["Skip the happy monthsary text.", 4.7, 8.4], ["Turn your photos and words into a little book.", 8.7, 12.0], [END_LINE, 12.5, 15.1]],
    },
    // 4. Birthdays and anniversaries
    "stars-wish": {
      title: "Make a wish", duration: 15.4, theme: "t-party", groove: [2.6, 12.2], endAt: 12.2,
      kicker: [["Birthdays and anniversaries", 0.5, 4.3]],
      heads: [["Make a wish.", 0.7, 4.3], ["Light up<br>the stars.", 4.5, 8.7], ["A memory in<br>every star.", 8.9, 12.0]],
      phone: { at: 4.3, until: 12.0, clips: [{ name: "stars", at: 4.3, from: 0, rate: 0.85 }] },
      lettie: { keys: [[0, 540, 1010, 560], [4.0, 160, 1400, 250]], waving: [[0.9, 3.4]] },
      cues: [{ t: 4.3, kind: "whoosh", gain: 0.6 }, { t: at(4.3, 8, 0.85), kind: "chime" }, { t: at(4.3, 52, 0.85), kind: "sparkle" }],
      lines: [["Birthday? Anniversary? Make a wish.", 0.9, 4.2], ["Light up the stars, one memory in each.", 4.6, 8.7], ["Add your photos, your song and your words.", 9.0, 12.0], [END_LINE, 12.5, 15.1]],
    },
    // 5. Invitations
    "invite-gift": {
      title: "Invite them in style", duration: 15.4, theme: "t-sunset", groove: [2.6, 12.2], endAt: 12.2,
      kicker: [["Invitations", 0.5, 4.3]],
      heads: [["Invite them<br>in style.", 0.7, 4.3], ["Open it like<br>a gift.", 4.5, 8.7], ["Weddings. Debuts.<br>Baby showers.", 8.9, 12.0]],
      phone: { at: 4.3, until: 12.0, clips: [{ name: "gift", at: 4.3, from: 0, rate: 0.85 }] },
      lettie: { keys: [[0, 540, 1010, 560], [4.0, 160, 1400, 250]], waving: [[0.9, 3.4]] },
      cues: [{ t: 4.3, kind: "whoosh", gain: 0.6 }, { t: at(4.3, 20, 0.85), kind: "pop" }, { t: at(4.3, 60, 0.85), kind: "sparkle" }],
      lines: [["Planning a party? Invite them in style.", 0.9, 4.2], ["A wrapped surprise with the details inside.", 4.6, 8.7], ["Weddings, debuts, christenings and baby showers.", 9.0, 12.0], [END_LINE, 12.5, 15.1]],
    },
  };
  // Halloween (Oct 17-31): trick or treat, narrated like the others; its backdrop floats the season's emoji instead of hearts
  VIDEOS["halloween-knock"] = {
    title: "Trick or treat?", duration: 15.4, theme: "t-halloween", glyphs: ["🎃", "🦇", "👻"], groove: [2.6, 12.2], endAt: 12.2,
    kicker: [["Halloween", 0.5, 4.3]],
    heads: [["Trick<br>or treat?", 0.7, 4.3], ["Knock three<br>times.", 4.5, 8.7], ["Your treat is<br>inside.", 8.9, 12.0]],
    phone: { at: 4.3, until: 12.0, clips: [{ name: "halloween", at: 4.3, from: 0, rate: 1 }] },
    lettie: { keys: [[0, 540, 1010, 560], [4.0, 160, 1400, 250]], waving: [[0.9, 3.4]] },
    cues: [{ t: 4.3, kind: "whoosh", gain: 0.6 }, { t: at(4.3, 14), kind: "tap" }, { t: at(4.3, 40), kind: "tap" }, { t: at(4.3, 66), kind: "tap" }, { t: at(4.3, 96), kind: "sparkle" }],
    lines: [["Trick or treat? Make them a Halloween surprise.", 0.9, 4.2], ["A haunted door. Knock three times.", 4.6, 8.7], ["It creaks open on your photos and your letter.", 9.0, 12.0], ["Love of My Life. Start free.", 12.5, 15.1]],
  };
  VIDEOS["halloween-lock"] = {
    title: "Only they can open it", duration: 15.4, theme: "t-halloween", glyphs: ["🎃", "🦇", "👻"], groove: [2.6, 12.2], endAt: 12.2,
    kicker: [["Halloween love letter", 0.5, 4.3]],
    heads: [["Spooky<br>and sweet.", 0.7, 4.3], ["Locked with a<br>question.", 4.5, 8.7], ["Only they can<br>open it.", 8.9, 12.0]],
    phone: { at: 4.3, until: 12.0, clips: [{ name: "halloweenlock", at: 4.3, from: 0, rate: 0.8 }] },
    lettie: { keys: [[0, 540, 1010, 560], [4.0, 160, 1400, 250]], waving: [[0.9, 3.4]] },
    cues: [{ t: 4.3, kind: "whoosh", gain: 0.6 }, { t: at(4.3, 76, 0.8), kind: "chime" }, { t: at(4.3, 104, 0.8), kind: "sparkle" }],
    lines: [["Make this Halloween spooky and sweet.", 0.9, 4.2], ["Lock your letter with a question only they know.", 4.6, 8.7], ["Then watch them open it. Boo.", 9.0, 12.0], ["Love of My Life. Start free.", 12.5, 15.1]],
  };

  // The week of 31 Oct - 6 Nov 2026 (marketing/social/week-nov/): one video per occasion, each narrated by Lettie in that occasion's
  // outfit (V.look -> week-nov/looks/lettie-<look>.svg; her body and face are the master's), over a clip of the real demo page
  // dressed as that occasion (week-nov/footage.py). Same timeline shape as the videos above.
  const week = (id, o) => {
    VIDEOS[id] = {
      duration: 15.4, groove: [2.6, 12.2], endAt: 12.2, lettie: { keys: [[0, 540, 1010, 560], [4.0, 160, 1400, 250]], waving: [[0.9, 3.4]] },
      ...o,
    };
  };
  week("wk-birthday", {
    title: "Happy birthday", theme: "t-party", look: "birthday", glyphs: ["🎉", "✨", "🎈"], note: "Free to build and preview. This template: Sweetheart or Forever.",
    kicker: [["Birthday", 0.5, 4.3]],
    heads: [["Happy<br>birthday!", 0.7, 4.3], ["Open the<br>surprise box.", 4.5, 8.7], ["Your photos and<br>wishes inside.", 8.9, 12.0]],
    phone: { at: 4.3, until: 12.0, clips: [{ name: "wk-birthday", at: 4.3, from: 0, rate: 0.85 }] },
    cues: [{ t: 4.3, kind: "whoosh", gain: 0.6 }, { t: at(4.3, 20, 0.85), kind: "pop" }, { t: at(4.3, 60, 0.85), kind: "sparkle" }],
    lines: [["Happy birthday! Skip the plain text message.", 0.9, 4.2], ["Make a surprise box that opens on your photos.", 4.6, 8.7], ["Add your photos and your birthday wishes.", 9.0, 12.0], [END_LINE, 12.5, 15.1]],
  });
  week("wk-invitation", {
    title: "You're invited", theme: "t-navy", look: "invitation", note: "Free to build and preview. Send it with a plan.",
    kicker: [["Invitations", 0.5, 4.3]],
    heads: [["You're<br>invited.", 0.7, 4.3], ["Tap the seal<br>to open it.", 4.5, 8.7], ["A link or<br>a QR code.", 8.9, 12.0]],
    phone: { at: 4.3, until: 12.0, clips: [{ name: "wk-invitation", at: 4.3, from: 0, rate: 0.6 }] },
    cues: [{ t: 4.3, kind: "whoosh", gain: 0.6 }, { t: at(4.3, 8, 0.6), kind: "pop" }, { t: at(4.3, 22, 0.6), kind: "paper" }, { t: at(4.3, 60, 0.6), kind: "sparkle" }],
    lines: [["You're invited! An invitation with no printing.", 0.9, 4.2], ["Guests tap the seal, and the details open.", 4.6, 8.7], ["Share a link or a QR code. Edit it any time.", 9.0, 12.0], [END_LINE, 12.5, 15.1]],
  });
  week("wk-wedding", {
    title: "For the day you say I do", theme: "t-wedding", look: "wedding", glyphs: ["♥", "✦"], note: "Free to build and preview. This template: Sweetheart or Forever.",
    kicker: [["Wedding", 0.5, 4.3]],
    heads: [["For the day<br>you say I do.", 0.7, 4.3], ["Your story,<br>page by page.", 4.5, 8.7], ["A little book<br>of your love.", 8.9, 12.0]],
    phone: { at: 4.5, until: 12.0, clips: [{ name: "wk-wedding", at: 4.5, from: 0, rate: 0.75 }] },
    cues: [{ t: 4.5, kind: "whoosh", gain: 0.6 }, { t: at(4.5, 8, 0.75), kind: "page" }, { t: at(4.5, 46, 0.75), kind: "page" }, { t: at(4.5, 84, 0.75), kind: "page" }],
    lines: [["For the day you say I do.", 0.9, 4.2], ["Tell how it happened, page by page, in a storybook.", 4.7, 8.7], ["Then share it with everyone who loves you.", 9.0, 12.0], [END_LINE, 12.5, 15.1]],
  });
  week("wk-anniversary", {
    title: "Another year together", theme: "t-gold", look: "anniversary", glyphs: ["✦", "♥"], note: "Free to build and preview. This template: Sweetheart or Forever.",
    kicker: [["Anniversary", 0.5, 4.3]],
    heads: [["Another year<br>together.", 0.7, 4.3], ["A star for every<br>memory.", 4.5, 8.7], ["Light up your<br>love story.", 8.9, 12.0]],
    phone: { at: 4.3, until: 12.0, clips: [{ name: "wk-anniversary", at: 4.3, from: 0, rate: 0.85 }] },
    cues: [{ t: 4.3, kind: "whoosh", gain: 0.6 }, { t: at(4.3, 8, 0.85), kind: "chime" }, { t: at(4.3, 52, 0.85), kind: "sparkle" }],
    lines: [["Another year together? Celebrate it properly.", 0.9, 4.2], ["Light up the stars, one memory in each.", 4.6, 8.7], ["Add your photos, your song and your words.", 9.0, 12.0], [END_LINE, 12.5, 15.1]],
  });
  week("wk-partner", {
    title: "Sweet on someone", theme: "", look: "partner", note: "Free to build and preview. This template: Sweetheart or Forever.",
    kicker: [["Boyfriend / Girlfriend", 0.5, 4.3]],
    heads: [["Sweet on<br>someone?", 0.7, 4.3], ["Scratch to find<br>the question.", 4.5, 8.7], ["They scratch.<br>They smile.", 8.9, 12.0]],
    phone: { at: 4.5, until: 12.0, clips: [{ name: "wk-partner", at: 4.5, from: 2, rate: 0.8 }] },
    cues: [{ t: 4.5, kind: "whoosh", gain: 0.6 }, { t: at(4.5, 40, 0.8, 2), kind: "tap" }, { t: at(4.5, 76, 0.8, 2), kind: "sparkle" }],
    lines: [["Sweet on someone? Tell them properly.", 0.9, 4.2], ["Hide your message under a scratch card.", 4.7, 8.7], ["They scratch, they smile, they feel loved.", 9.0, 12.0], [END_LINE, 12.5, 15.1]],
  });

  // Interactive reels: the viewer is asked to DO something in the comments (pick, tag, vote, finish a sentence), so there is
  // no phone clip; V.scene says when the on-screen game plays and V.kind says which one (SCENES below).
  const INTERACTIVE = {
    "pick-a-letter": {
      title: "Pick a letter", duration: 15.4, theme: "t-gold", groove: [2.6, 12.2], endAt: 12.2, kind: "pick", scene: { at: 4.3, until: 12.1 },
      note: "Free to build and preview.",
      kicker: [["Choose your envelope", 0.5, 4.3]],
      heads: [["Pick a<br>letter.", 0.7, 4.3], ["Comment 1,<br>2 or 3.", 4.5, 8.8], ["I'll write<br>back.", 9.0, 12.0]],
      lettie: { keys: [[0, 540, 1010, 560], [4.0, 160, 1400, 250]], waving: [[0.9, 3.4]] },
      cues: [{ t: 4.4, kind: "whoosh", gain: 0.6 }, { t: 4.7, kind: "pop" }, { t: 4.95, kind: "pop" }, { t: 5.2, kind: "pop" }, { t: 6.6, kind: "tap" }, { t: 7.7, kind: "tap" }, { t: 8.8, kind: "tap" }, { t: 9.4, kind: "sparkle" }],
      lines: [["Pick a letter. Any letter.", 0.9, 4.2], ["Comment one, two, or three.", 4.6, 8.7], ["And I'll write you back.", 9.0, 12.0], [END_LINE, 12.5, 15.1]],
    },
    "scratch-tag": {
      title: "Scratch to reveal", duration: 15.4, theme: "", groove: [2.6, 12.2], endAt: 12.2, kind: "scratch", scene: { at: 4.3, until: 12.1 },
      note: "Free to build and preview.",
      kicker: [["Scratch and tag", 0.5, 4.3]],
      heads: [["Scratch<br>to reveal.", 0.7, 4.3], ["Who is it<br>for?", 4.5, 9.2], ["Tag them.", 9.4, 12.0]],
      lettie: { keys: [[0, 540, 1010, 560], [4.0, 160, 1400, 250]], waving: [[0.9, 3.4]] },
      cues: [{ t: 4.4, kind: "whoosh", gain: 0.6 }, { t: 5.2, kind: "paper" }, { t: 6.4, kind: "paper" }, { t: 7.6, kind: "paper" }, { t: 8.8, kind: "paper" }, { t: 9.6, kind: "sparkle" }],
      lines: [["Scratch the card to reveal.", 0.9, 4.3], ["Someone is getting a love letter.", 4.7, 9.2], ["Tag them in the comments.", 9.5, 12.0], [END_LINE, 12.5, 15.1]],
    },
    "this-or-that": {
      title: "This or that", duration: 15.4, theme: "t-party", groove: [2.6, 12.2], endAt: 12.2, kind: "thisthat", scene: { at: 4.2, until: 12.1 },
      note: "Free to build and preview.",
      kicker: [["Love letter edition", 0.5, 4.2]],
      heads: [["This or<br>that?", 0.7, 4.2], ["Pick one<br>each round.", 4.4, 12.0]],
      lettie: { keys: [[0, 540, 1010, 560], [4.0, 160, 1400, 250]], waving: [[0.9, 3.4]] },
      cues: [{ t: 4.3, kind: "whoosh", gain: 0.6 }, { t: 6.35, kind: "tap" }, { t: 8.2, kind: "tap" }, { t: 10.05, kind: "tap" }, { t: 11.9, kind: "sparkle" }],
      lines: [["This or that. Love letter edition.", 0.9, 4.2], ["Four rounds. Pick one each time.", 4.5, 8.9], ["Comment your four, like 1, 2, 1, 2.", 9.2, 12.0], [END_LINE, 12.5, 15.1]],
    },
    "finish-line": {
      title: "Finish the sentence", duration: 15.4, theme: "t-sunset", groove: [2.6, 12.2], endAt: 12.2, kind: "fill", scene: { at: 4.2, until: 12.1 },
      note: "Free to build and preview.",
      kicker: [["Your turn", 0.5, 4.2]],
      heads: [["Finish the<br>sentence.", 0.7, 4.2], ["Fill in the<br>blank.", 4.4, 9.0], ["Say it<br>sweetly.", 9.2, 12.0]],
      lettie: { keys: [[0, 540, 1010, 560], [4.0, 160, 1400, 250]], waving: [[0.9, 3.4]] },
      cues: [{ t: 4.3, kind: "whoosh", gain: 0.6 }, { t: 7.0, kind: "chime" }, { t: 9.0, kind: "chime" }, { t: 11.2, kind: "sparkle" }],
      lines: [["Finish the sentence. Our song is…", 0.9, 4.2], ["Here are a few ideas.", 4.6, 8.8], ["Now type yours in the comments.", 9.2, 12.0], [END_LINE, 12.5, 15.1]],
    },
  };
  Object.assign(VIDEOS, INTERACTIVE);
  const V = VIDEOS[ID];
  if (!V) throw new Error(`unknown video ${ID}`);
  const DURATION = V.duration;
  const BLINKS = [1.7, 3.9, 6.4, 9.1, 11.8, 13.6];

  // ── helpers ────────────────────────────────────────────────────────────────
  const clamp = (x, a = 0, b = 1) => Math.min(b, Math.max(a, x));
  const span = (t, a, b) => clamp((t - a) / (b - a));
  const ease = (x) => 1 - (1 - clamp(x)) ** 3;
  const smooth = (x) => { x = clamp(x); return x < 0.5 ? 4 * x ** 3 : 1 - (-2 * x + 2) ** 3 / 2; };
  const back = (x) => { x = clamp(x); const c = 1.70158; return 1 + (c + 1) * (x - 1) ** 3 + c * (x - 1) ** 2; };
  const mix = (a, b, k) => a + (b - a) * k;
  const vis = (t, a, b, din = 0.45, dout = 0.3) => Math.min(ease(span(t, a, a + din)), 1 - ease(span(t, b - dout, b)));
  const el = (tag, cls = "", html = "") => { const n = document.createElement(tag); if (cls) n.className = cls; if (html) n.innerHTML = html; return n; };
  const stage = document.getElementById("stage");
  stage.className = `portrait ${V.theme}`;
  const add = (node) => { node.classList.add("abs"); stage.appendChild(node); return node; };
  function place(node, x, y, { s = 1, r = 0, o = 1, anchor = "c" } = {}) {
    const ay = anchor === "t" ? "0%" : "-50%";
    node.style.transform = `translate(${x}px, ${y}px) translate(-50%, ${ay}) scale(${s}) rotate(${r}deg)`;
    node.style.opacity = String(clamp(o));
    node.style.visibility = o <= 0.001 ? "hidden" : "visible";
  }
  let seed = 7;
  const rand = () => ((seed = (seed * 16807) % 2147483647) - 1) / 2147483646;

  // ── build ──────────────────────────────────────────────────────────────────
  const glow = add(el("div", "glow"));
  const GLYPHS = V.glyphs || ["♥"];
  const hearts = Array.from({ length: 24 }, (_, k) => {
    const h = add(el("div", "bg-heart", GLYPHS[k % GLYPHS.length]));
    h.style.fontSize = `${14 + rand() * 30}px`;
    return { node: h, x: rand() * W, speed: 30 + rand() * 50, phase: rand() * (H + 200), sway: 10 + rand() * 30, o: 0.18 + rand() * 0.3 };
  });

  const PW = 440, PH = Math.round((PW - 32) / 0.4621 + 32);
  const phone = add(el("div", "phone", '<div class="screen"><img alt="" /><span class="island"></span></div>'));
  phone.style.width = `${PW}px`;
  phone.style.height = `${PH}px`;
  const clipImg = phone.querySelector("img");
  if (!V.phone) V.phone = null; // interactive reels play V.scene instead of a clip

  const kickers = V.kicker.map(([t, a, b]) => ({ node: add(el("div", "kicker txt", t)), a, b }));
  const heads = V.heads.map(([t, a, b]) => {
    const n = add(el("div", `headline txt${t.length > 34 ? " long" : ""}`, t));
    return { node: n, a, b };
  });
  const caption = add(el("div", "caption txt"));
  const lettie = add(el("div", ""));
  lettie.id = "lettie";
  const bursts = [V.endAt + 0.3, ...V.cues.filter((c) => c.kind === "sparkle").map((c) => c.t)].map(() => Array.from({ length: 12 }, (_, k) => {
    const h = add(el("div", "burst-heart", GLYPHS[k % GLYPHS.length]));
    h.style.fontSize = `${22 + (k % 4) * 8}px`;
    return { node: h, a: (Math.PI * 2 * k) / 12 + rand() * 0.4, d: 160 + rand() * 120 };
  }));
  const BURST_AT = [V.endAt + 0.3, ...V.cues.filter((c) => c.kind === "sparkle").map((c) => c.t)];
  const endLogo = add(el("div", "logo txt", `${HEART}<span>Love of My Life</span>`));
  endLogo.querySelector("span").style.fontSize = "96px";
  const endTag = add(el("div", "sub txt", "A love letter they unlock."));
  const endUrl = add(el("div", "url txt", URL_TEXT));
  const endQr = add(el("div", "card qr-card", `<img alt="" src="${QR}" />`));
  endQr.style.width = "300px";
  const endNote = add(el("div", "note txt", V.note || "Sample page shown. Free to build and preview."));


  // ── interactive scenes: each returns draw(t, v) where v is its fade (0..1) ───────────────────────────────
  const SCENES = {
    // three sealed envelopes; a finger picks each in turn, then they all wiggle
    pick() {
      const items = [["a promise", 190], ["a memory", 540], ["a surprise", 890]].map(([label, x], i) => {
        const env = add(el("div", "env", '<div class="flap"></div><div class="seal">♥</div>'));
        const num = add(el("div", "num txt", String(i + 1)));
        const name = add(el("div", "env-label txt", label));
        return { env, num, name, x, i };
      });
      const hand = add(el("div", "finger", "👆"));
      return {
        draw(t, v) {
          items.forEach(({ env, num, name, x, i }) => {
            const pop = back(span(t, 4.4 + i * 0.25, 5.1 + i * 0.25));
            const mine = 6.2 + i * 1.1; // the finger visits this envelope
            const lift = Math.sin(Math.PI * span(t, mine - 0.3, mine + 0.8)) * 34;
            const wiggle = t > 9.1 ? Math.sin(t * 9 + i * 2) * 5 * smooth(span(t, 9.1, 9.5)) : 0;
            place(env, x, 880 - lift, { s: pop * (1 + lift / 300), r: wiggle + (i - 1) * 2, o: v * span(t, 4.4 + i * 0.25, 4.6 + i * 0.25) });
            const pulse = 1 + (t > 9.1 ? 0.06 * Math.sin(t * 6 + i) : 0);
            place(num, x, 1040, { s: pop * pulse, o: v * pop });
            place(name, x, 1130, { s: 1, o: v * pop });
          });
          const idx = clamp(Math.floor((t - 6.2) / 1.1), 0, 2), k = span(t, 5.9, 6.2);
          const tap = Math.sin(Math.PI * clamp(((t - 6.2) % 1.1) / 0.8)) * 26;
          place(hand, items[idx].x + 40, 1000 - tap, { s: 1.2, o: v * k * (1 - span(t, 9.0, 9.3)) });
        },
      };
    },
    // a gold scratch card: the foil is a canvas, erased along a zig-zag as time passes
    scratch() {
      const CW = 840, CH = 460;
      const base = add(el("div", "scratch-base", '<span class="txt scratch-text">Tag the person<br>you\'d send it to 💌</span>'));
      const foil = add(el("canvas", "scratch-foil"));
      foil.width = CW; foil.height = CH;
      const ctx = foil.getContext("2d");
      const coin = add(el("div", "coin", "🪙"));
      const pts = [];
      for (let r = 0; r < 5; r++) {
        const y = 62 + r * 84, xs = r % 2 ? [800, 40] : [40, 800];
        for (let k = 0; k <= 16; k++) pts.push([mix(xs[0], xs[1], k / 16), y + Math.sin(k * 1.3 + r) * 12]);
      }
      const seg = pts.slice(1).map((q, i) => Math.hypot(q[0] - pts[i][0], q[1] - pts[i][1]));
      const total = seg.reduce((a, b) => a + b, 0);
      return {
        draw(t, v) {
          const pin = back(span(t, 4.3, 5.0));
          const cx = 540, cy = 950;
          const reveal = ease(span(t, 9.3, 9.8));
          place(base, cx, cy, { s: pin * (1 + 0.04 * reveal), o: v * span(t, 4.3, 4.6) });
          const g = ctx.createLinearGradient(0, 0, CW, CH);
          g.addColorStop(0, "#f7d98c"); g.addColorStop(0.5, "#fff0b8"); g.addColorStop(1, "#d9a441");
          ctx.globalCompositeOperation = "source-over";
          ctx.clearRect(0, 0, CW, CH);
          ctx.fillStyle = g; ctx.fillRect(0, 0, CW, CH);
          ctx.fillStyle = "rgba(255,255,255,.35)";
          for (let k = 0; k < 90; k++) ctx.fillRect((k * 197) % CW, (k * 131) % CH, 3, 3);
          const sweep = mix(-300, CW + 300, (t * 0.35) % 1);
          const band = ctx.createLinearGradient(sweep - 80, 0, sweep + 80, 0);
          band.addColorStop(0, "rgba(255,255,255,0)"); band.addColorStop(0.5, "rgba(255,255,255,.55)"); band.addColorStop(1, "rgba(255,255,255,0)");
          ctx.fillStyle = band; ctx.fillRect(0, 0, CW, CH);
          ctx.fillStyle = "rgba(120,70,10,.55)"; ctx.font = "600 64px Inter, sans-serif"; ctx.textAlign = "center"; ctx.textBaseline = "middle";
          ctx.fillText("SCRATCH HERE", CW / 2, CH / 2);
          const prog = span(t, 5.0, 9.2) * total;
          let head = pts[0];
          ctx.globalCompositeOperation = "destination-out";
          ctx.lineCap = ctx.lineJoin = "round"; ctx.lineWidth = 118;
          ctx.beginPath(); ctx.moveTo(pts[0][0], pts[0][1]);
          let run = 0;
          for (let i = 0; i < seg.length && run < prog; i++) {
            const f = Math.min(1, (prog - run) / seg[i]);
            head = [mix(pts[i][0], pts[i + 1][0], f), mix(pts[i][1], pts[i + 1][1], f)];
            ctx.lineTo(head[0], head[1]);
            run += seg[i];
          }
          ctx.stroke();
          const fade = 1 - span(t, 9.3, 9.8); // whatever foil is left falls away
          place(foil, cx, cy, { s: pin, o: v * span(t, 4.3, 4.6) * (reveal >= 1 ? 0 : 1) });
          foil.style.opacity = String(clamp(v * span(t, 4.3, 4.6) * fade));
          place(coin, cx - CW / 2 + head[0], cy - CH / 2 + head[1] + 20, { s: 1.25, r: -25 + Math.sin(t * 14) * 8, o: v * span(t, 4.9, 5.2) * (1 - span(t, 9.2, 9.4)) });
        },
      };
    },
    // two choices per round, a shrinking timer, four rounds
    thisthat() {
      const ROUNDS = [["💌", "Envelope", "🎟️", "Scratch card"], ["✨", "Stars", "📖", "Storybook"], ["🎵", "Our song", "📸", "Our photos"], ["🌙", "Midnight", "☀️", "Morning"]];
      const START = 4.5, LEN = 1.85;
      const tag = add(el("div", "round txt", ""));
      const a = add(el("div", "choice", ""));
      const b = add(el("div", "choice", ""));
      const or = add(el("div", "or", "or"));
      const bar = add(el("div", "timer", "<i></i>"));
      let shownRound = -1;
      return {
        draw(t, v) {
          const r = clamp(Math.floor((t - START) / LEN), 0, ROUNDS.length - 1);
          if (r !== shownRound) {
            shownRound = r;
            const [e1, l1, e2, l2] = ROUNDS[r];
            a.innerHTML = `<span class="n txt">1</span><span class="e">${e1}</span><span class="l txt">${l1}</span>`;
            b.innerHTML = `<span class="n txt">2</span><span class="e">${e2}</span><span class="l txt">${l2}</span>`;
            tag.textContent = `Round ${r + 1} of ${ROUNDS.length}`;
          }
          const local = t - (START + r * LEN);
          const pop = back(span(local, 0, 0.4));
          const o = v * span(t, START - 0.1, START + 0.2);
          place(tag, 540, 720, { o });
          place(a, 290, 930, { s: pop, r: -3 * (1 - pop), o: o * pop });
          place(b, 790, 930, { s: pop, r: 3 * (1 - pop), o: o * pop });
          place(or, 540, 930, { s: pop, o: o * pop });
          place(bar, 540, 1190, { o });
          bar.firstChild.style.transform = `scaleX(${clamp(1 - local / (LEN - 0.15))})`;
        },
      };
    },
    // a note card where answers are typed, then wiped, then typed again
    fill() {
      const card = add(el("div", "note-card", '<div class="lead txt">Our song is</div><div class="typed txt"></div>'));
      const typed = card.querySelector(".typed");
      const ANSWERS = [["the one we sang in the car", 4.7, 7.3], ["playing at 2 a.m.", 7.3, 9.4], ["still on repeat", 9.4, 11.3]];
      return {
        draw(t, v) {
          const pin = back(span(t, 4.2, 4.9));
          place(card, 540, 950, { s: pin, r: -2 * pin, o: v * span(t, 4.2, 4.5) });
          let text = "";
          for (const [ans, a, b] of ANSWERS) {
            if (t >= a && t < b) {
              const typing = clamp((t - a - 0.2) / (0.6 * ans.length / 14)); // about 14 letters a second
              text = ans.slice(0, Math.round(ans.length * typing));
            }
          }
          const blink = Math.floor(t * 2) % 2 === 0 ? "|" : "";
          typed.textContent = text ? text + blink : (t >= 11.3 ? "________" : "") + blink;
        },
      };
    },
  };
  const scene = V.kind ? SCENES[V.kind]() : null;

  let svg = null;
  const part = (id) => svg.querySelector(`#${id}`);
  let mouth = [];
  let lines = [];
  let shots = { clips: {} };
  let shown = "";

  // ── drawing one frame ──────────────────────────────────────────────────────
  function lettieAt(t) {
    const keys = [...V.lettie.keys, [V.endAt, 540, 650, 440]];
    let cur = keys[0].slice(1);
    for (let i = 0; i < keys.length; i++) {
      const [start, ...p] = keys[i];
      if (t >= start) {
        const prev = i ? keys[i - 1].slice(1) : p;
        const k = smooth(span(t, start, start + 0.7));
        cur = [mix(prev[0], p[0], k), mix(prev[1], p[1], k), mix(prev[2], p[2], k)];
      }
    }
    const [x, y, size] = cur;
    const pop = back(span(t, 0.15, 0.8));
    const beat = (t * 88) / 60;
    const bob = -Math.abs(Math.sin(Math.PI * beat)) * size * 0.018;
    const squash = 1 + Math.sin(Math.PI * 2 * beat) * 0.012;
    lettie.style.width = lettie.style.height = `${size}px`;
    place(lettie, x, y + bob, { s: pop, o: span(t, 0.12, 0.3) });
    part("l-body").setAttribute("transform", `translate(200 330) scale(${2 - squash} ${squash}) translate(-200 -330)`);

    const waving = V.lettie.waving.some(([a, b]) => t > a && t < b);
    const love = t >= V.endAt + 0.3;
    let left = 22 + Math.sin(t * 2.1) * 5, right = -22 - Math.sin(t * 2.1 + 1) * 5;
    if (waving) right = -125 + Math.sin(t * Math.PI * 2 * 1.9) * 22;
    if (love) {
      const k = smooth(span(t, V.endAt + 0.3, V.endAt + 0.8));
      left = mix(left, -34, k);
      right = mix(right, 34, k);
    }
    part("l-arm-l").setAttribute("transform", `translate(${svg.dataset.armL || "76 238"}) rotate(${left.toFixed(2)})`);
    part("l-arm-r").setAttribute("transform", `translate(${svg.dataset.armR || "324 238"}) rotate(${right.toFixed(2)})`);
    const hugK = love ? smooth(span(t, V.endAt + 0.35, V.endAt + 0.85)) : 0;
    part("l-hug").setAttribute("opacity", hugK.toFixed(3));
    part("l-hug").setAttribute("transform", `translate(200 338) scale(${(0.6 + 0.4 * back(hugK)).toFixed(3)})`);
    let open = 1;
    for (const b of BLINKS) open = Math.min(open, 1 - Math.sin(Math.PI * span(t, b, b + 0.16)));
    const happy = love ? smooth(span(t, V.endAt + 0.9, V.endAt + 1.2)) * (1 - smooth(span(t, DURATION - 1.0, DURATION - 0.7))) : 0;
    part("l-eyes").setAttribute("transform", `translate(0 262) scale(1 ${Math.max(0.08, open * (1 - happy)).toFixed(3)}) translate(0 -262)`);
    part("l-eyes-happy").setAttribute("opacity", happy.toFixed(3));
    const m = mouth.length ? mouth[Math.min(mouth.length - 1, Math.round(t * FPS))] || 0 : 0;
    part("l-mouth-open").setAttribute("opacity", m > 0.06 ? "1" : "0");
    part("l-mouth-open").setAttribute("transform", `translate(200 288) scale(${(0.8 + 0.25 * m).toFixed(3)} ${(0.25 + 0.95 * m).toFixed(3)})`);
    part("l-mouth-smile").setAttribute("opacity", m > 0.06 ? "0" : "1");
    return { x, y, size };
  }

  function clipAt(t) {
    const ph = V.phone;
    const c = [...ph.clips].reverse().find((k) => t >= k.at) || ph.clips[0];
    const frames = (shots.clips[c.name] || { frames: 1 }).frames;
    const f = t < c.at ? c.from : c.from + (t - c.at) * FPS * c.rate;
    return { name: c.name, frame: Math.min(frames - 1, Math.max(0, Math.floor(f))) };
  }

  async function seek(t) {
    place(glow, mix(W * 0.3, W * 0.7, (Math.sin(t * 0.25) + 1) / 2), mix(H * 0.25, H * 0.45, (Math.cos(t * 0.2) + 1) / 2), { o: 0.9 });
    for (const h of hearts) {
      const y = H + 80 - ((t * h.speed + h.phase) % (H + 200));
      place(h.node, h.x + Math.sin(t * 0.7 + h.phase) * h.sway, y, { o: h.o * span(t, 0, 0.8) });
    }
    const me = lettieAt(t);

    // the phone slides up while the clip plays, and leaves for the end card
    const ph = V.phone;
    let src = "";
    if (ph) {
      const pin = back(span(t, ph.at - 0.3, ph.at + 0.45)), pout = smooth(span(t, ph.until - 0.25, ph.until + 0.1));
      place(phone, 540, 1040 + (1 - pin) * 220 + 70 * pout, { s: 1 - 0.12 * pout, o: Math.min(span(t, ph.at - 0.3, ph.at - 0.1), 1 - pout), r: (1 - pin) * 4 });
      const clip = clipAt(t);
      src = `${SHOTS}${clip.name}/${String(clip.frame).padStart(4, "0")}.jpg`;
    } else {
      place(phone, 540, 1040, { o: 0 });
      scene.draw(t, vis(t, V.scene.at, V.scene.until, 0.4, 0.3));
    }

    kickers.forEach(({ node, a, b }) => { const v = vis(t, a, b); place(node, 540, 235 + (1 - v) * 20, { o: v }); });
    heads.forEach(({ node, a, b }) => { const v = vis(t, a, b); place(node, 540, 300 + (1 - v) * 40, { o: v, anchor: "t" }); });

    BURST_AT.forEach((when, b) => {
      const k = span(t, when, when + 1.3);
      for (const h of bursts[b]) {
        const d = ease(k) * h.d;
        place(h.node, me.x + Math.cos(h.a) * d, me.y - me.size * 0.15 + Math.sin(h.a) * d - k * 60, { o: k > 0 && k < 1 ? 1 - k : 0, s: 0.6 + 0.6 * ease(k) });
      }
    });

    // captions, for watching with the sound off
    const line = lines.find((l) => t >= l.at && t < l.at + l.duration + 0.25);
    caption.textContent = line ? line.text : "";
    const cv = line ? Math.min(span(t, line.at, line.at + 0.15), 1 - span(t, line.at + line.duration + 0.1, line.at + line.duration + 0.25)) : 0;
    place(caption, 540, 1590, { o: cv });

    // end card
    const e = V.endAt;
    const ev = (d) => ease(span(t, e + d, e + d + 0.5));
    place(endLogo, 540, 935 + (1 - ev(0.5)) * 30, { o: ev(0.5) });
    place(endTag, 540, 1030 + (1 - ev(0.8)) * 30, { o: ev(0.8) });
    place(endUrl, 540, 1135 + (1 - ev(1.1)) * 30, { o: ev(1.1) });
    place(endQr, 540, 1345 + (1 - ev(1.4)) * 30, { o: ev(1.4) });
    place(endNote, 540, 1690, { o: ev(1.6) * 0.9 });

    if (src && src !== shown) {
      shown = src;
      clipImg.src = src;
      await clipImg.decode().catch(() => {});
    }
  }

  // Words that leave the safe area, or sit on the phone, Lettie or other words.
  function audit(t) {
    seekSync(t);
    const problems = [];
    const MX = W * 0.05, MY = 140;
    const words = Array.from(stage.querySelectorAll(".txt")).filter((n) => getComputedStyle(n).visibility !== "hidden").map((n) => [n, n.getBoundingClientRect()]);
    const solid = [];
    if (getComputedStyle(phone).visibility !== "hidden") solid.push([phone, phone.getBoundingClientRect()]);
    if (getComputedStyle(endQr).visibility !== "hidden") solid.push([endQr, endQr.getBoundingClientRect()]);
    const l = lettie.getBoundingClientRect();
    solid.push([lettie, { left: l.left + l.width * 0.12, right: l.right - l.width * 0.12, top: l.top + l.height * 0.18, bottom: l.bottom - l.height * 0.1 }]);
    words.forEach(([n, r], i) => {
      const name = (n.textContent || n.className).trim().slice(0, 28);
      if (r.left < MX - 1 || r.right > W - MX + 1 || r.top < MY - 1 || r.bottom > H - 160 + 1) problems.push(`"${name}" outside the safe area (${Math.round(r.left)},${Math.round(r.top)} ${Math.round(r.width)}x${Math.round(r.height)})`);
      for (const [m, s] of solid) {
        if (r.left < s.right - 4 && r.right > s.left + 4 && r.top < s.bottom - 4 && r.bottom > s.top + 4) { problems.push(`"${name}" overlaps ${m === lettie ? "Lettie" : m === endQr ? "the QR code" : "the phone"}`); break; }
      }
      words.slice(i + 1).forEach(([m, q]) => {
        if (r.left < q.right - 2 && r.right > q.left + 2 && r.top < q.bottom - 2 && r.bottom > q.top + 2) problems.push(`"${name}" overlaps "${(m.textContent || "").trim().slice(0, 20)}"`);
      });
    });
    return problems;
  }
  // audit needs a synchronous draw: the frame is drawn without waiting for the image
  function seekSync(t) { shown = shown || "x"; seek(t); }

  const ready = (async () => {
    const [svgText, index, said] = await Promise.all([
      fetch(V.look ? `/marketing/social/week-nov/looks/lettie-${V.look}.svg` : "/marketing/promo/lettie.svg").then((r) => r.text()),
      fetch(`${SHOTS}shots.json`).then((r) => (r.ok ? r.json() : { clips: {} })),
      fetch(`/marketing/out/social/reels/voice/${ID}/voice.json`).then((r) => (r.ok ? r.json() : null)).catch(() => null),
    ]);
    lettie.innerHTML = svgText;
    svg = lettie.querySelector("svg");
    shots = index;
    lines = said ? said.lines : V.lines.map(([text, a, b]) => ({ text, at: a, duration: b - a - 0.3 }));
    await Promise.all(["italic 500 40px \"Cormorant Garamond\"", "500 40px Inter", "600 40px Inter", "400 40px Inter"].map((f) => document.fonts.load(f)));
    await document.fonts.ready;
    await seek(0);
    await Promise.all(Array.from(stage.querySelectorAll("img")).map((i) => (i.complete ? null : i.decode().catch(() => {}))));
    return true;
  })();

  window.reel = {
    id: ID, title: V.title, fps: FPS, duration: DURATION, cues: V.cues, groove: V.groove, endAt: V.endAt, ready,
    lines: V.lines, seek, audit,
    setMouth: (values) => { mouth = values; },
    fonts: () => [["Cormorant Garamond", "italic 500"], ["Inter", "500"]].map(([f, w]) => [f, document.fonts.check(`${w} 40px "${f}"`)]),
  };
})();
