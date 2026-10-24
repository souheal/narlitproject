"use client";

import { useEffect, useRef } from "react";
import * as THREE from "three";

/**
 * Login backdrop for NarLit: a 3D open book of nonprofit stories. A sun rises behind it with a
 * heart in front (reading that gives back), pages riffle and float up out of the book, and the
 * whole scene tilts with the cursor. When `focus` carries an image, the camera flies into the
 * pages and a photo of the place opens out of them like a portal.
 *
 * Three.js, one WebGL canvas under the z-index 1 card, pointer-events none. Pauses when the tab
 * is hidden; reduced-motion users get a single still frame.
 */

export interface BookFocus {
  label: string;
  /** If set, the camera flies into the book and opens onto this photo. */
  image?: string;
  credit?: string;
  /** Where the photo's subject sits horizontally, 0..1 across the image (default centre). */
  imageFocusX?: number;
}

const TEAL = "#11a8bc";
const TEAL_DEEP = "#087f93";
const ORANGE = "#ff7a00";

// Book geometry, in world units at scale 1.
const PAGE_W = 1.3;
const PAGE_H = 1.85;
const CAM_Z = 6;
const BASE_FOV = 35;

// Flight timing (ms): leaving is quicker than arriving.
const FLIGHT_MS = 7200;
const LEAVE_MS = 2200;

const clamp01 = (v: number) => Math.max(0, Math.min(1, v));
const easeInOutCubic = (t: number) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
const easeInOutSine = (t: number) => -(Math.cos(Math.PI * t) - 1) / 2;

// Pages curve up from the gutter toward the outer edge, with a small dip at the spine.
function bend(d: number) {
  return 0.46 * Math.pow(d / PAGE_W, 1.6) - 0.05 * Math.exp(-d * 9);
}

function bentPage(sign: 1 | -1, extraW = 0, extraH = 0) {
  const w = PAGE_W + extraW;
  const geo = new THREE.PlaneGeometry(w, PAGE_H + extraH, 28, 6);
  geo.rotateX(-Math.PI / 2);
  geo.translate((sign * w) / 2, 0, 0);
  const pos = geo.attributes.position as THREE.BufferAttribute;
  for (let i = 0; i < pos.count; i++) pos.setY(i, bend(Math.abs(pos.getX(i))));
  pos.needsUpdate = true;
  geo.computeVertexNormals();
  return geo;
}

// A made-up magazine page drawn on a canvas: kicker, headline, a photo block and body text.
function pageTexture(side: "l" | "r", seed: number) {
  let s = seed;
  const rnd = () => ((s = (s * 16807) % 2147483647) / 2147483647);
  const W = 512;
  const H = 724;
  const c = document.createElement("canvas");
  c.width = W;
  c.height = H;
  const g = c.getContext("2d")!;
  g.fillStyle = "#fdfefe";
  g.fillRect(0, 0, W, H);

  const rr = (x: number, y: number, w: number, h: number, r: number, fill: string) => {
    g.fillStyle = fill;
    g.beginPath();
    g.roundRect(x, y, w, h, r);
    g.fill();
  };

  const x0 = 56;
  const colW = W - 112;
  rr(x0, 62, 74, 14, 7, ORANGE);
  rr(x0, 96, colW * (0.7 + rnd() * 0.25), 30, 8, TEAL_DEEP);
  rr(x0, 140, colW * (0.4 + rnd() * 0.3), 30, 8, TEAL_DEEP);

  // photo block with a tiny sunrise
  const py = 196;
  const grad = g.createLinearGradient(x0, py, x0 + colW, py + 170);
  grad.addColorStop(0, TEAL);
  grad.addColorStop(1, "#ffb066");
  g.fillStyle = grad;
  g.beginPath();
  g.roundRect(x0, py, colW, 170, 14);
  g.fill();
  g.fillStyle = "rgba(255,255,255,0.9)";
  g.beginPath();
  g.arc(x0 + colW * (0.3 + rnd() * 0.4), py + 104, 26, Math.PI, 0);
  g.fill();

  // body text
  let y = py + 170 + 34;
  while (y < H - 70) {
    const lines = 3 + Math.floor(rnd() * 3);
    for (let i = 0; i < lines && y < H - 70; i++) {
      const last = i === lines - 1;
      rr(x0, y, colW * (last ? 0.3 + rnd() * 0.4 : 0.92 + rnd() * 0.08), 9, 4.5, "rgba(15,44,51,0.5)");
      y += 22;
    }
    y += 16;
  }

  // soft shadow toward the gutter
  const sh = g.createLinearGradient(side === "l" ? W : 0, 0, side === "l" ? W - 120 : 120, 0);
  sh.addColorStop(0, "rgba(8,60,70,0.34)");
  sh.addColorStop(1, "rgba(8,60,70,0)");
  g.fillStyle = sh;
  g.fillRect(0, 0, W, H);

  const tex = new THREE.CanvasTexture(c);
  tex.anisotropy = 8;
  tex.colorSpace = THREE.SRGBColorSpace;
  return tex;
}

function radialTexture(stops: [number, string][]) {
  const c = document.createElement("canvas");
  c.width = c.height = 256;
  const g = c.getContext("2d")!;
  const grad = g.createRadialGradient(128, 128, 0, 128, 128, 128);
  for (const [o, col] of stops) grad.addColorStop(o, col);
  g.fillStyle = grad;
  g.fillRect(0, 0, 256, 256);
  const t = new THREE.CanvasTexture(c);
  t.colorSpace = THREE.SRGBColorSpace;
  return t;
}

function raysTexture() {
  const c = document.createElement("canvas");
  c.width = c.height = 512;
  const g = c.getContext("2d")!;
  g.translate(256, 256);
  const grad = g.createRadialGradient(0, 0, 40, 0, 0, 256);
  grad.addColorStop(0, "rgba(255,190,100,0.55)");
  grad.addColorStop(1, "rgba(255,160,60,0)");
  g.fillStyle = grad;
  const n = 22;
  for (let i = 0; i < n; i++) {
    const a = (i / n) * Math.PI * 2;
    const half = 0.05 + (i % 2) * 0.03;
    g.beginPath();
    g.moveTo(0, 0);
    g.lineTo(Math.cos(a - half) * 260, Math.sin(a - half) * 260);
    g.lineTo(Math.cos(a + half) * 260, Math.sin(a + half) * 260);
    g.closePath();
    g.fill();
  }
  const t = new THREE.CanvasTexture(c);
  t.colorSpace = THREE.SRGBColorSpace;
  return t;
}

export default function InteractiveBook({ focus = null }: { focus?: BookFocus | null }) {
  const mountRef = useRef<HTMLDivElement>(null);
  const focusRef = useRef<BookFocus | null>(focus);
  focusRef.current = focus;

  useEffect(() => {
    const mount = mountRef.current;
    if (!mount) return;

    const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    const pixel = Math.min(window.devicePixelRatio || 1, 2);
    renderer.setPixelRatio(pixel);
    renderer.setClearColor(0x000000, 0);
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    mount.appendChild(renderer.domElement);
    renderer.domElement.style.display = "block";
    mount.style.opacity = "0";
    mount.style.transition = "opacity 1.2s ease";

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(BASE_FOV, 1, 0.1, 60);
    camera.position.z = CAM_Z;

    const disposables: { dispose: () => void }[] = [];
    const track = <T extends { dispose: () => void }>(o: T) => (disposables.push(o), o);

    // ---------- lights ----------
    scene.add(new THREE.AmbientLight(0xffffff, 1.15));
    const key = new THREE.DirectionalLight(0xffffff, 1.5);
    key.position.set(-3, 4, 5);
    scene.add(key);
    const warm = new THREE.PointLight(0xff9a3c, 14, 9, 1.6);
    warm.position.set(0, 1.4, 0.6);
    const rim = new THREE.DirectionalLight(0x6fe6f0, 0.8);
    rim.position.set(4, 2, -3);
    scene.add(rim);

    // ---------- the book ----------
    const root = new THREE.Group(); // positioned beside the sign-in card
    scene.add(root);
    root.add(warm);
    const book = new THREE.Group();
    book.rotation.x = 0.95; // tipped toward the viewer
    root.add(book);

    const texL = track(pageTexture("l", 11));
    const texR = track(pageTexture("r", 29));
    const geoL = track(bentPage(-1));
    const geoR = track(bentPage(1));
    const pageMat = (map: THREE.Texture) => track(new THREE.MeshStandardMaterial({ map, roughness: 0.92, metalness: 0 }));
    const matL = pageMat(texL);
    const matR = pageMat(texR);
    book.add(new THREE.Mesh(geoL, matL), new THREE.Mesh(geoR, matR));

    // page-block edges under the top pages
    const edgeMat = track(new THREE.MeshStandardMaterial({ color: 0xe6f1f2, roughness: 1 }));
    for (let i = 1; i <= 4; i++) {
      for (const g of [geoL, geoR]) {
        const m = new THREE.Mesh(g, edgeMat);
        m.position.y = -0.02 * i;
        book.add(m);
      }
    }
    // cover, slightly larger than the pages
    const coverMat = track(new THREE.MeshStandardMaterial({ color: 0x0a7486, roughness: 0.6, side: THREE.DoubleSide }));
    const coverL = track(bentPage(-1, 0.14, 0.16));
    const coverR = track(bentPage(1, 0.14, 0.16));
    for (const g of [coverL, coverR]) {
      const m = new THREE.Mesh(g, coverMat);
      m.position.y = -0.14;
      book.add(m);
    }
    const spine = new THREE.Mesh(
      track(new THREE.BoxGeometry(0.16, 0.2, PAGE_H + 0.16)),
      track(new THREE.MeshStandardMaterial({ color: 0x055e6e, roughness: 0.5 })),
    );
    spine.position.set(0, -0.12, 0);
    book.add(spine);

    // the page that keeps turning
    const FLIP_SEG = 24;
    const flipGeo = track(new THREE.PlaneGeometry(PAGE_W, PAGE_H, FLIP_SEG, 6));
    flipGeo.rotateX(-Math.PI / 2);
    flipGeo.translate(PAGE_W / 2, 0, 0);
    const baseD = Float32Array.from({ length: flipGeo.attributes.position.count }, (_, i) => flipGeo.attributes.position.getX(i));
    const baseZ = Float32Array.from({ length: flipGeo.attributes.position.count }, (_, i) => flipGeo.attributes.position.getZ(i));
    const flipMat = track(
      new THREE.MeshStandardMaterial({ map: texR, roughness: 0.92, side: THREE.DoubleSide, transparent: true }),
    );
    const flipMesh = new THREE.Mesh(flipGeo, flipMat);
    flipMesh.position.y = 0.012;
    book.add(flipMesh);

    function poseFlip(A: number) {
      const pos = flipGeo.attributes.position as THREE.BufferAttribute;
      const curl = 0.55;
      for (let i = 0; i < pos.count; i++) {
        const d = baseD[i];
        const a = A + curl * (d / PAGE_W) * Math.sin(A);
        pos.setXYZ(i, d * Math.cos(a), d * Math.sin(a) + bend(d) * (1 - Math.sin(A)), baseZ[i]);
      }
      pos.needsUpdate = true;
      flipGeo.computeVertexNormals();
    }

    // ---------- sun and heart ----------
    const sun = new THREE.Group();
    sun.position.set(0, 1.05, -0.7);
    root.add(sun);
    const raysMat = track(
      new THREE.MeshBasicMaterial({
        map: track(raysTexture()),
        transparent: true,
        depthWrite: false,
        blending: THREE.AdditiveBlending,
      }),
    );
    const rays = new THREE.Mesh(track(new THREE.PlaneGeometry(4.6, 4.6)), raysMat);
    sun.add(rays);
    const glowMat = track(
      new THREE.SpriteMaterial({
        map: track(
          radialTexture([
            [0, "rgba(255,244,210,1)"],
            [0.22, "rgba(255,180,80,0.95)"],
            [0.55, "rgba(255,120,20,0.3)"],
            [1, "rgba(255,120,20,0)"],
          ]),
        ),
        transparent: true,
        depthWrite: false,
      }),
    );
    const glow = new THREE.Sprite(glowMat);
    glow.scale.set(2.5, 2.5, 1);
    sun.add(glow);
    const core = new THREE.Mesh(
      track(new THREE.SphereGeometry(0.5, 48, 32)),
      track(new THREE.MeshBasicMaterial({ color: 0xffa83a })),
    );
    sun.add(core);

    const heartShape = new THREE.Shape();
    heartShape.moveTo(0, -0.5);
    heartShape.bezierCurveTo(-0.95, 0.12, -0.5, 0.85, 0, 0.36);
    heartShape.bezierCurveTo(0.5, 0.85, 0.95, 0.12, 0, -0.5);
    const heartGeo = track(
      new THREE.ExtrudeGeometry(heartShape, {
        depth: 0.22,
        bevelEnabled: true,
        bevelThickness: 0.09,
        bevelSize: 0.09,
        bevelSegments: 5,
        curveSegments: 28,
      }),
    );
    heartGeo.center();
    const heart = new THREE.Mesh(
      heartGeo,
      track(new THREE.MeshStandardMaterial({ color: 0xffffff, emissive: 0xffc98a, emissiveIntensity: 0.55, roughness: 0.3 })),
    );
    heart.scale.setScalar(0.3);
    heart.position.set(0, 1.05, -0.2);
    root.add(heart);

    // soft ground shadow so the book sits on the page
    const shadow = new THREE.Mesh(
      track(new THREE.PlaneGeometry(4.4, 1.6)),
      track(
        new THREE.MeshBasicMaterial({
          map: track(
            radialTexture([
              [0, "rgba(6,70,82,0.42)"],
              [0.6, "rgba(6,70,82,0.14)"],
              [1, "rgba(6,70,82,0)"],
            ]),
          ),
          transparent: true,
          depthWrite: false,
        }),
      ),
    );
    shadow.position.set(0, -1.05, -0.4);
    root.add(shadow);

    // ---------- stories rising out of the book ----------
    interface Floater { mesh: THREE.Mesh; x0: number; ph: number; speed: number; mat: THREE.MeshBasicMaterial }
    const floaters: Floater[] = [];
    const floatGeo = track(new THREE.PlaneGeometry(0.42, 0.6));
    for (let i = 0; i < 12; i++) {
      const mat = track(
        new THREE.MeshBasicMaterial({
          map: i % 2 ? texL : texR,
          transparent: true,
          side: THREE.DoubleSide,
          depthWrite: false,
        }),
      );
      const mesh = new THREE.Mesh(floatGeo, mat);
      root.add(mesh);
      floaters.push({ mesh, x0: (Math.random() - 0.5) * 2.1, ph: Math.random() * 10, speed: 0.00005 + Math.random() * 0.00004, mat });
    }

    // golden dust
    const dustN = 140;
    const dustPos = new Float32Array(dustN * 3);
    const dustSeed = Array.from({ length: dustN }, () => ({ x: (Math.random() - 0.5) * 4.2, z: (Math.random() - 0.3) * 1.6, ph: Math.random(), sp: 0.00006 + Math.random() * 0.00007 }));
    const dustGeo = track(new THREE.BufferGeometry());
    dustGeo.setAttribute("position", new THREE.BufferAttribute(dustPos, 3));
    const dust = new THREE.Points(
      dustGeo,
      track(
        new THREE.PointsMaterial({
          color: 0xffb04a,
          size: 0.045,
          transparent: true,
          opacity: 0.7,
          depthWrite: false,
          blending: THREE.AdditiveBlending,
        }),
      ),
    );
    dust.frustumCulled = false;
    root.add(dust);

    // ---------- DOM layers: vignette, photo (portal), bloom, credit ----------
    const vignette = document.createElement("div");
    Object.assign(vignette.style, {
      position: "absolute",
      inset: "0",
      background: "radial-gradient(ellipse at 50% 50%, rgba(5,46,53,0) 35%, rgba(5,46,53,0.55) 100%)",
      opacity: "0",
    });
    mount.appendChild(vignette);

    const photo = document.createElement("div");
    Object.assign(photo.style, {
      position: "absolute",
      inset: "0",
      overflow: "hidden",
      opacity: "0",
      clipPath: "circle(0% at 50% 50%)",
      willChange: "clip-path, opacity",
    });
    const photoImg = document.createElement("div");
    Object.assign(photoImg.style, {
      position: "absolute",
      top: "-4%",
      bottom: "-4%",
      backgroundSize: "cover",
      backgroundPosition: "center 35%",
      willChange: "transform",
    });
    const photoShade = document.createElement("div");
    Object.assign(photoShade.style, {
      position: "absolute",
      inset: "0",
      background:
        "radial-gradient(ellipse at 50% 45%, rgba(255,255,255,0) 40%, rgba(5,46,53,0.35) 100%), linear-gradient(180deg, rgba(255,255,255,0) 55%, rgba(5,46,53,0.28) 100%)",
    });
    photo.append(photoImg, photoShade);
    mount.appendChild(photo);

    const bloom = document.createElement("div");
    Object.assign(bloom.style, {
      position: "absolute",
      inset: "0",
      background: "radial-gradient(circle at 50% 50%, rgba(255,226,180,0.95) 0%, rgba(255,170,90,0.45) 28%, rgba(255,255,255,0) 62%)",
      opacity: "0",
    });
    mount.appendChild(bloom);

    const credit = document.createElement("div");
    Object.assign(credit.style, {
      position: "absolute",
      right: "16px",
      bottom: "12px",
      font: "600 11px 'Segoe UI', Tahoma, sans-serif",
      color: "rgba(255,255,255,0.85)",
      textShadow: "0 1px 4px rgba(0,0,0,0.5)",
      opacity: "0",
    });
    mount.appendChild(credit);

    let shownFocus: BookFocus | null = null;
    let photoFor: BookFocus | null = null;
    let photoFocusX = 0.5;

    // ---------- layout ----------
    let w = 1;
    let h = 1;
    let bx = 0;
    let by = 0;
    function layoutPhoto() {
      // keep the photo's subject clear of the sign-in card: on wide screens the image is widened
      // and shifted so the subject sits ~76% across the page
      const wide = w >= 900;
      const imgW = wide ? w * 1.7 : w * 1.08;
      const subjectX = wide ? 0.76 : 0.5;
      photoImg.style.width = `${imgW}px`;
      photoImg.style.left = `${subjectX * w - photoFocusX * imgW}px`;
    }
    const resize = () => {
      w = window.innerWidth;
      h = window.innerHeight;
      renderer.setSize(w, h);
      camera.aspect = w / h;
      camera.updateProjectionMatrix();
      const visH = 2 * CAM_Z * Math.tan((BASE_FOV * Math.PI) / 360);
      const visW = visH * camera.aspect;
      const wide = w >= 900;
      // beside the card on wide screens; centred and a touch smaller on narrow ones
      bx = wide ? visW * 0.29 : 0;
      by = wide ? -0.25 : -visH * 0.28;
      root.scale.setScalar(Math.min(1, (wide ? visW * 0.25 : visW * 0.7) / (2 * PAGE_W)));
      layoutPhoto();
      if (reduced) render(performance.now());
    };

    const mouse = { x: 0, y: 0 };
    const cur = { x: 0, y: 0 };
    const onMove = (e: PointerEvent) => {
      mouse.x = (e.clientX / w) * 2 - 1;
      mouse.y = (e.clientY / h) * 2 - 1;
    };

    // ---------- frame ----------
    let diveT = 0;
    let held = 0;
    let flipClock = 0;
    let riseClock = 0;
    let raysAngle = 0;
    let last = performance.now();
    let lookX = 0;
    let lookY = 0;
    let raf = 0;
    const camFrom = new THREE.Vector3();
    const camTo = new THREE.Vector3();

    function render(now: number) {
      const dt = Math.min(now - last, 60);
      last = now;
      const f = focusRef.current;
      if (f) shownFocus = f;
      const wantDive = !!(f && f.image);
      diveT = clamp01(diveT + (wantDive ? dt / FLIGHT_MS : -dt / LEAVE_MS));
      const zoom = easeInOutCubic(diveT);

      cur.x += (mouse.x - cur.x) * 0.05;
      cur.y += (mouse.y - cur.y) * 0.05;

      // book: idle bob plus a tilt that follows the cursor (calmer while flying in)
      const calm = 1 - zoom * 0.85;
      root.position.set(bx, by + Math.sin(now * 0.0009) * 0.04, 0);
      root.rotation.y = -0.22 + cur.x * 0.32 * calm;
      root.rotation.x = cur.y * 0.12 * calm;
      key.position.x = -3 + cur.x * 3;

      // the turning page, riffling faster as the camera falls in
      // (phases are accumulated, not scaled from `now`: changing the rate must never make them jump)
      flipClock += dt * 0.00031 * (1 + zoom * 1.2);
      const cycle = flipClock % 1;
      const p = clamp01((cycle - 0.1) / 0.55);
      flipMesh.visible = p > 0 && p < 1;
      if (flipMesh.visible) {
        poseFlip(easeInOutSine(p) * Math.PI);
        flipMat.opacity = Math.min(1, p * 8) * Math.min(1, (1 - p) * 8);
      }

      // sun swells, rays turn, heart spins
      const sunK = 1 + zoom * 1.8;
      sun.scale.setScalar(sunK);
      raysAngle += dt * 0.00006 * (1 + zoom * 1.5);
      rays.rotation.z = raysAngle;
      raysMat.opacity = 0.75;
      glow.scale.setScalar(2.5 + Math.sin(now * 0.002) * 0.1);
      warm.intensity = 14 + zoom * 30;
      heart.rotation.y = Math.sin(now * 0.0007) * 0.6;
      heart.position.y = 1.05 + Math.sin(now * 0.0012) * 0.06;
      heart.scale.setScalar(0.3 * (1 + zoom * 0.8));

      // stories rising
      riseClock += dt * (1 + zoom * 0.6);
      for (const fl of floaters) {
        const t = (fl.ph + riseClock * fl.speed) % 1;
        fl.mesh.position.set(fl.x0 + Math.sin(t * 6 + fl.ph) * 0.35, 0.25 + t * 3.3, 0.6 + Math.cos(t * 5 + fl.ph) * 0.4);
        fl.mesh.rotation.set(t * 2.6 + fl.ph, t * 4 + fl.ph, Math.sin(t * 7 + fl.ph) * 0.5);
        fl.mat.opacity = Math.pow(Math.sin(Math.PI * t), 0.6) * 0.95;
      }
      for (let i = 0; i < dustN; i++) {
        const sd = dustSeed[i];
        const t = (sd.ph + now * sd.sp) % 1;
        dustPos[i * 3] = sd.x + Math.sin(t * 9 + i) * 0.12;
        dustPos[i * 3 + 1] = -0.6 + t * 3.6;
        dustPos[i * 3 + 2] = sd.z;
      }
      dustGeo.attributes.position.needsUpdate = true;

      // camera: from the usual view to just above the open pages
      camFrom.set(cur.x * 0.15, -cur.y * 0.1, CAM_Z);
      camTo.set(bx, by + 0.55, 1.5);
      camera.position.lerpVectors(camFrom, camTo, zoom);
      lookX = bx * zoom;
      lookY = by * zoom;
      camera.lookAt(lookX, lookY, 0);
      const fov = BASE_FOV - zoom * 7;
      if (camera.fov !== fov) {
        camera.fov = fov;
        camera.updateProjectionMatrix();
      }

      if (zoom < 0.995) renderer.render(scene, camera);

      // the photo opens out of the pages like a portal
      vignette.style.opacity = String(Math.sin(Math.min(1, zoom * 1.15) * Math.PI) * 0.9);
      if (shownFocus && shownFocus.image) {
        if (photoFor !== shownFocus) {
          photoFor = shownFocus;
          photoFocusX = shownFocus.imageFocusX ?? 0.5;
          photoImg.style.backgroundImage = `url(${shownFocus.image})`;
          credit.textContent = shownFocus.credit ?? "";
          layoutPhoto();
        }
        const reveal = clamp01((zoom - 0.62) / 0.36);
        const eased = reveal * reveal * (3 - 2 * reveal);
        photo.style.opacity = String(Math.min(1, eased * 2));
        photo.style.clipPath = `circle(${eased * 150}% at 50% 50%)`;
        held = eased > 0.99 ? held + dt : 0;
        const creep = Math.min(0.06, held * 0.000006);
        photoImg.style.transform = `translate(${-cur.x * 14}px, ${-cur.y * 10}px) scale(${1 + (1 - eased) * 0.28 + creep})`;
        bloom.style.opacity = String(Math.sin(reveal * Math.PI) * 0.6);
        credit.style.opacity = String(Math.max(0, (eased - 0.6) * 2.5));
      } else {
        photo.style.opacity = "0";
        credit.style.opacity = "0";
        bloom.style.opacity = "0";
      }
    }

    const frame = (now: number) => {
      render(now);
      raf = requestAnimationFrame(frame);
    };

    resize();
    window.addEventListener("resize", resize);
    window.addEventListener("pointermove", onMove, { passive: true });
    const onVisibility = () => {
      cancelAnimationFrame(raf);
      if (!document.hidden && !reduced) {
        last = performance.now();
        raf = requestAnimationFrame(frame);
      }
    };
    document.addEventListener("visibilitychange", onVisibility);
    if (reduced) render(performance.now());
    else raf = requestAnimationFrame(frame);
    requestAnimationFrame(() => (mount.style.opacity = "1"));

    return () => {
      cancelAnimationFrame(raf);
      window.removeEventListener("resize", resize);
      window.removeEventListener("pointermove", onMove);
      document.removeEventListener("visibilitychange", onVisibility);
      disposables.forEach((d) => d.dispose());
      renderer.dispose();
      for (const el of [renderer.domElement, vignette, photo, bloom, credit]) el.remove();
    };
  }, []);

  return (
    <div
      ref={mountRef}
      aria-hidden="true"
      style={{ position: "fixed", inset: 0, zIndex: 0, pointerEvents: "none" }}
    />
  );
}
