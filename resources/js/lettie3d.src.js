// Lettie in 3D. Source for public/assets/lettie3d.js (build: see marketing/brand/LETTIE.md).
//
// Mounts on any element with [data-lettie3d]; data-theme is default | halloween | christmas | valentine. It reads the
// `talking` and `love` classes on the element (or its [data-lettie] ancestor), the same hooks the 2D art used, so ui.js
// needs no changes. Without WebGL, or with the script blocked, the 2D SVG inside the element stays.
import {
  AmbientLight, CapsuleGeometry, Color, ConeGeometry, CylinderGeometry, DirectionalLight, ExtrudeGeometry, Group,
  HemisphereLight, MathUtils, Mesh, MeshPhysicalMaterial, MeshStandardMaterial, PerspectiveCamera, PointLight,
  QuadraticBezierCurve3, Scene, Shape, SphereGeometry, TorusGeometry, TubeGeometry, Vector3, WebGLRenderer, CatmullRomCurve3,
} from "three";
import { RoundedBoxGeometry } from "three/examples/jsm/geometries/RoundedBoxGeometry.js";

// What changes with the season. Her body, face and heart seal stay hers; clothes, glow and the seal's tint change.
const THEMES = {
  default: { seal: 0xe0607e, glow: 0xf2a7b8, key: 0xffffff, accent: 0xf0cfa8 },
  halloween: { seal: 0xf0802a, glow: 0xff8a3d, key: 0xffe2c4, accent: 0x6b3fa0 },
  christmas: { seal: 0xc4283c, glow: 0xffd9a0, key: 0xfff4e8, accent: 0x1f7a4d },
  valentine: { seal: 0xe0407a, glow: 0xff9bc0, key: 0xfff0f4, accent: 0xf0cfa8 },
};

const heartShape = (s = 1) => {
  const h = new Shape();
  h.moveTo(0, -0.55 * s);
  h.bezierCurveTo(-0.9 * s, 0.05 * s, -0.55 * s, 0.75 * s, 0, 0.3 * s);
  h.bezierCurveTo(0.55 * s, 0.75 * s, 0.9 * s, 0.05 * s, 0, -0.55 * s);
  return h;
};
const heart = (size, material, depth = 0.22) => {
  const g = new ExtrudeGeometry(heartShape(size), { depth: depth * size, bevelEnabled: true, bevelThickness: 0.07 * size, bevelSize: 0.07 * size, bevelSegments: 6, curveSegments: 24 });
  g.center();
  return new Mesh(g, material);
};
const mat = (color, extra = {}) => new MeshStandardMaterial({ color, roughness: 0.55, metalness: 0, ...extra });
const gloss = (color, extra = {}) => new MeshPhysicalMaterial({ color, roughness: 0.28, clearcoat: 0.8, clearcoatRoughness: 0.2, ...extra });

const build = (theme) => {
  const T = THEMES[theme] || THEMES.default;
  const root = new Group();
  const body = new Group();
  root.add(body);
  const paper = mat(0xfff6ec, { roughness: 0.7 });
  const trim = mat(0xf3c2cd, { roughness: 0.6 });
  const dark = mat(0x3a1f2b, { roughness: 0.25 });
  const blush = mat(0xf7a1b5, { roughness: 0.9 });

  // the envelope
  const env = new Mesh(new RoundedBoxGeometry(2.6, 2.1, 0.95, 8, 0.28), paper);
  body.add(env);
  // the flap: a shallow V laid on the front
  const flapShape = new Shape();
  flapShape.moveTo(-1.18, 0.98);
  flapShape.lineTo(1.18, 0.98);
  flapShape.lineTo(0, 0);
  flapShape.closePath();
  const flap = new Mesh(new ExtrudeGeometry(flapShape, { depth: 0.04, bevelEnabled: true, bevelThickness: 0.02, bevelSize: 0.03, bevelSegments: 3 }), mat(0xfbd6dc, { roughness: 0.65 }));
  flap.position.set(0, 0, 0.475);
  body.add(flap);
  // the seal
  const seal = heart(0.5, gloss(T.seal), 0.3);
  seal.position.set(0, 0.02, 0.62);
  body.add(seal);
  const shine = new Mesh(new SphereGeometry(0.05, 16, 16), mat(0xffffff, { emissive: 0xffffff, emissiveIntensity: 0.4 }));
  shine.scale.set(1.2, 0.7, 0.4);
  shine.position.set(-0.13, 0.12, 0.76);
  body.add(shine);

  // the curl on top, ending in a small heart
  const curl = new Mesh(new TubeGeometry(new CatmullRomCurve3([new Vector3(0, 1.0, 0), new Vector3(-0.1, 1.28, 0.02), new Vector3(0.12, 1.5, 0.02), new Vector3(0.02, 1.7, 0.02)]), 24, 0.045, 10, false), trim);
  body.add(curl);
  const tip = heart(0.2, gloss(0xf2a7b8), 0.2);
  tip.position.set(0.05, 1.8, 0.02);
  tip.rotation.z = -0.3;
  body.add(tip);

  // face
  const face = new Group();
  face.position.set(0, -0.62, 0.5);
  body.add(face);
  const eyes = new Group();
  face.add(eyes);
  const eyeParts = [];
  for (const x of [-0.5, 0.5]) {
    const eye = new Group();
    eye.position.set(x, 0, 0);
    const ball = new Mesh(new SphereGeometry(0.19, 32, 24), dark);
    ball.scale.set(0.82, 1.08, 0.5);
    const hi = new Mesh(new SphereGeometry(0.065, 16, 16), mat(0xffffff, { emissive: 0xffffff, emissiveIntensity: 0.6 }));
    hi.position.set(-0.05, 0.08, 0.1);
    const hi2 = new Mesh(new SphereGeometry(0.03, 12, 12), mat(0xffffff, { emissive: 0xffffff, emissiveIntensity: 0.4 }));
    hi2.position.set(0.06, -0.07, 0.1);
    eye.add(ball, hi, hi2);
    eyes.add(eye);
    eyeParts.push(eye);
  }
  const happy = new Group();
  for (const x of [-0.5, 0.5]) {
    const arc = new Mesh(new TubeGeometry(new QuadraticBezierCurve3(new Vector3(x - 0.17, -0.02, 0.05), new Vector3(x, 0.24, 0.05), new Vector3(x + 0.17, -0.02, 0.05)), 16, 0.032, 8, false), dark);
    happy.add(arc);
  }
  happy.visible = false;
  face.add(happy);
  for (const x of [-0.82, 0.82]) {
    const cheek = new Mesh(new SphereGeometry(0.17, 24, 16), blush);
    cheek.scale.set(1.1, 0.62, 0.3);
    cheek.position.set(x, -0.18, -0.02);
    face.add(cheek);
  }
  const smile = new Mesh(new TubeGeometry(new QuadraticBezierCurve3(new Vector3(-0.17, -0.12, 0.05), new Vector3(0, -0.3, 0.05), new Vector3(0.17, -0.12, 0.05)), 16, 0.032, 8, false), dark);
  face.add(smile);
  const open = new Mesh(new SphereGeometry(0.13, 24, 16), mat(0x7a2a3e, { roughness: 0.4 }));
  open.scale.set(1, 0.9, 0.4);
  open.position.set(0, -0.2, 0.05);
  open.visible = false;
  face.add(open);

  // arms and feet
  const arm = (x) => {
    const g = new Group();
    g.position.set(x, -0.1, 0);
    const m = new Mesh(new CapsuleGeometry(0.17, 0.36, 8, 16), paper);
    m.position.y = -0.3;
    g.add(m);
    g.rotation.z = x < 0 ? 0.35 : -0.35;
    body.add(g);
    return g;
  };
  const armL = arm(-1.38);
  const armR = arm(1.38);
  for (const x of [-0.6, 0.6]) {
    const foot = new Mesh(new SphereGeometry(0.3, 24, 16), trim);
    foot.scale.set(1, 0.5, 0.9);
    foot.position.set(x, -1.12, 0.1);
    body.add(foot);
  }

  // the heart she hugs when she is clicked
  const hug = heart(1.0, gloss(0xee6687), 0.4);
  hug.position.set(0, -0.35, 0.95);
  hug.scale.setScalar(0.001);
  body.add(hug);

  // clothes
  const hat = new Group();
  hat.position.set(0.02, 1.02, 0);
  const wear = {
    halloween() {
      const felt = mat(0x2b1a45, { roughness: 0.85 });
      const brim = new Mesh(new CylinderGeometry(0.95, 1.0, 0.07, 40), felt);
      const cone = new Mesh(new ConeGeometry(0.58, 1.25, 36), felt);
      cone.position.y = 0.64;
      cone.rotation.z = 0.16;
      const band = new Mesh(new CylinderGeometry(0.6, 0.66, 0.16, 36), mat(0xf0802a, { roughness: 0.5 }));
      band.position.y = 0.14;
      const buckle = new Mesh(new RoundedBoxGeometry(0.22, 0.2, 0.06, 3, 0.03), mat(0xf4c542, { metalness: 0.7, roughness: 0.3 }));
      buckle.position.set(0, 0.14, 0.64);
      hat.add(brim, cone, band, buckle);
      hat.rotation.z = -0.12;
    },
    christmas() {
      const red = mat(0xc4283c, { roughness: 0.8 });
      const white = mat(0xfdfbf7, { roughness: 0.95 });
      const cone = new Mesh(new ConeGeometry(0.72, 1.3, 36), red);
      cone.position.set(0.12, 0.6, 0);
      cone.rotation.z = -0.28;
      const rim = new Mesh(new TorusGeometry(0.72, 0.17, 16, 40), white);
      rim.rotation.x = Math.PI / 2;
      rim.position.y = 0.04;
      const pom = new Mesh(new SphereGeometry(0.2, 20, 16), white);
      pom.position.set(0.58, 1.12, 0);
      hat.add(cone, rim, pom);
    },
    valentine() {
      const gold = mat(0xf0cfa8, { metalness: 0.8, roughness: 0.25 });
      const halo = new Mesh(new TorusGeometry(0.62, 0.055, 16, 48), gold);
      halo.rotation.x = Math.PI / 2 - 0.25;
      halo.position.y = 0.45;
      halo.userData.halo = true;
      hat.add(halo);
      for (const s of [-1, 1]) {
        const wing = new Mesh(new SphereGeometry(0.6, 24, 16), mat(0xffffff, { roughness: 0.6 }));
        wing.scale.set(0.9, 1.35, 0.14);
        wing.position.set(s * 1.65, 0.05, -0.55);
        wing.rotation.z = s * -0.55;
        wing.userData.wing = s;
        body.add(wing);
      }
    },
  };
  if (wear[theme]) wear[theme]();
  body.add(hat);
  return { root, body, eyes: eyeParts, happy, smile, open, hug, armL, armR, hat, theme: T };
};

// the little extras that float around her
const sparkles = (theme, T) => {
  const g = new Group();
  const items = [];
  const kinds = {
    halloween: () => new Mesh(new SphereGeometry(0.09, 12, 12), mat(0xff8a3d, { emissive: 0xff7a1a, emissiveIntensity: 0.9 })),
    christmas: () => new Mesh(new SphereGeometry(0.07, 12, 12), mat(0xffffff, { emissive: 0xffffff, emissiveIntensity: 0.8 })),
    valentine: () => heart(0.16, gloss(0xff6f9b), 0.2),
    default: () => heart(0.12, gloss(0xf2a7b8), 0.2),
  };
  const make = kinds[theme] || kinds.default;
  for (let i = 0; i < 10; i += 1) {
    const m = make();
    const a = (i / 10) * Math.PI * 2;
    m.userData = { a, r: 2.2 + (i % 3) * 0.35, y: -0.9 + (i % 5) * 0.65, s: 0.6 + (i % 4) * 0.15, p: i * 1.7 };
    g.add(m);
    items.push(m);
  }
  return { group: g, items };
};

const mount = (el, options = {}) => {
  const reduce = matchMedia("(prefers-reduced-motion: reduce)").matches;
  const theme = options.theme || el.dataset.theme || "default";
  const host = el.closest("[data-lettie]") || el;
  const renderer = new WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: !!options.preserve });
  renderer.setPixelRatio(Math.min(devicePixelRatio || 1, options.maxDpr || 2));
  renderer.setClearColor(0x000000, 0);
  renderer.domElement.className = "lettie-canvas";
  renderer.domElement.setAttribute("aria-hidden", "true");

  const scene = new Scene();
  const camera = new PerspectiveCamera(30, 1, 0.1, 50);
  camera.position.set(0, 0.35, 10.5);
  camera.lookAt(0, 0.35, 0);
  const L = build(theme);
  scene.add(L.root);
  const fx = sparkles(theme, L.theme);
  scene.add(fx.group);
  scene.add(new AmbientLight(0xffffff, 0.55));
  scene.add(new HemisphereLight(0xffffff, L.theme.glow, 0.75));
  const key = new DirectionalLight(L.theme.key, 2.1);
  key.position.set(-3, 5, 7);
  scene.add(key);
  const rim = new PointLight(L.theme.glow, 40, 14);
  rim.position.set(3.5, 1.5, -3);
  scene.add(rim);

  const state = { tx: 0, ty: 0, rx: 0, ry: 0, loved: false, mouth: null, t0: performance.now() };
  const size = () => {
    const w = Math.max(el.clientWidth, 1);
    const h = Math.max(el.clientHeight || w, 1);
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
  };
  let raf = 0;
  let blinkAt = 0;
  const frame = (now) => {
    const t = (now - state.t0) / 1000;
    state.rx += (state.tx - state.rx) * 0.08;
    state.ry += (state.ty - state.ry) * 0.08;
    const love = host.classList.contains("love") || state.loved;
    const talking = host.classList.contains("talking") || options.talk;
    L.root.rotation.y = state.rx * 0.5 + Math.sin(t * 0.7) * 0.1;
    L.root.rotation.x = -state.ry * 0.25;
    L.root.position.y = Math.sin(t * 1.9) * 0.07 + (love ? Math.max(0, Math.sin((t * 6) % Math.PI)) * 0.12 : 0);
    L.body.scale.set(1 + Math.sin(t * 3.8) * 0.006, 1 - Math.sin(t * 3.8) * 0.01, 1);
    // blink about every three seconds
    if (t > blinkAt + 3 && !love) blinkAt = t + Math.random() * 1.5;
    const blink = t - blinkAt < 0.12 && t > blinkAt ? 0.1 : 1;
    L.eyes.forEach((e) => { e.scale.y = love ? 0.001 : blink; });
    L.happy.visible = love;
    if (state.mouth !== null) {
      // driven from outside (the video renders): 0 closed .. 1 wide open
      L.open.visible = state.mouth > 0.06;
      L.open.scale.set(1.1 + state.mouth * 0.5, 0.25 + state.mouth * 1.5, 0.4);
      L.smile.visible = !L.open.visible;
    } else {
      L.smile.visible = !talking || Math.floor(t * 6) % 2 === 0;
      L.open.visible = talking && Math.floor(t * 6) % 2 === 1;
    }
    L.hug.scale.setScalar(L.hug.scale.x + ((love ? 0.9 : 0.001) - L.hug.scale.x) * 0.18);
    L.hug.rotation.y = Math.sin(t * 2) * 0.25;
    L.armR.rotation.z = -0.35 - (options.wave ? 1 + Math.sin(t * 8) * 0.45 : 0) - (love ? 0.5 : 0);
    L.armL.rotation.z = 0.35 + (love ? 0.5 : 0);
    L.root.traverse((o) => {
      if (o.userData.wing) o.rotation.y = o.userData.wing * Math.sin(t * 5) * 0.28;
    });
    for (const m of fx.items) {
      const d = m.userData;
      const a = d.a + t * 0.35 * d.s;
      m.position.set(Math.cos(a) * d.r, d.y + Math.sin(t * d.s + d.p) * 0.25, Math.sin(a) * d.r * 0.5);
      m.scale.setScalar(0.8 + Math.sin(t * 2 + d.p) * 0.2);
      m.rotation.y = t * d.s;
    }
    renderer.render(scene, camera);
    if (!options.manual && (!reduce || options.preserve)) raf = requestAnimationFrame(frame);
  };

  const move = (e) => {
    const r = el.getBoundingClientRect();
    state.tx = MathUtils.clamp(((e.clientX - (r.left + r.width / 2)) / innerWidth) * 3, -1, 1);
    state.ty = MathUtils.clamp(((e.clientY - (r.top + r.height / 2)) / innerHeight) * 3, -1, 1);
  };
  if (!reduce) addEventListener("pointermove", move, { passive: true });
  new ResizeObserver(size).observe(el);

  el.appendChild(renderer.domElement);
  el.classList.add("lettie-3d");
  size();
  frame(performance.now());
  if (reduce && !options.preserve) renderer.render(scene, camera);

  return {
    canvas: renderer.domElement,
    pose: (p) => {
      state.loved = p === "love";
      options.wave = p === "hello";
      options.talk = p === "talk";
    },
    stop: () => cancelAnimationFrame(raf),
    // deterministic frame for the video renders: `at(seconds, mouthOpenness 0..1)`
    at: (seconds, mouth = 0) => {
      state.mouth = mouth;
      frame(state.t0 + seconds * 1000);
    },
    render: () => frame(performance.now()),
    destroy: () => {
      cancelAnimationFrame(raf);
      removeEventListener("pointermove", move);
      renderer.dispose();
      renderer.domElement.remove();
    },
  };
};

const supported = () => {
  try {
    const c = document.createElement("canvas");
    return !!(c.getContext("webgl2") || c.getContext("webgl"));
  } catch {
    return false;
  }
};

const auto = () => {
  if (!supported()) return;
  document.querySelectorAll("[data-lettie3d]").forEach((el) => {
    if (el.dataset.mounted) return;
    el.dataset.mounted = "1";
    mount(el);
  });
};

window.Lettie3D = { mount, themes: Object.keys(THEMES), supported };
if (!document.querySelector("[data-lettie3d-manual]")) {
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", auto);
  else auto();
}
