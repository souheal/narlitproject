"use client";

import { useEffect, useRef } from "react";
import * as THREE from "three";

/**
 * Login backdrop: a large relief globe of nonprofits. Real continents come from
 * a land mask and the relief from a hill-shaded elevation proxy (both in
 * /public/earth), recoloured in the brand palette. Orange hubs sit on real
 * cities and pulse; arcs of funding (streams of small orange dots) connect the
 * nearest ones. The globe turns slowly and eases toward the cursor with a
 * little parallax. The sign-in card is frosted glass, so the globe reads
 * through it.
 *
 * Three.js, one WebGL canvas under the z-index 1 card, pointer-events none.
 * Pauses when the tab is hidden; reduced-motion users get a single still frame.
 */

const TEAL = new THREE.Color("#11a8bc");
const ORANGE = new THREE.Color("#ff7a00");

// [lon, lat] of the cities that carry a hub.
const CITIES: [number, number][] = [
  [36.3, 33.5], // Damascus
  [35.9, 31.95], // Amman
  [35.5, 33.9], // Beirut
  [31.2, 30.0], // Cairo
  [36.8, -1.3], // Nairobi
  [3.4, 6.5], // Lagos
  [18.4, -33.9], // Cape Town
  [-0.1, 51.5], // London
  [13.4, 52.5], // Berlin
  [28.9, 41.0], // Istanbul
  [77.2, 28.6], // Delhi
  [90.4, 23.8], // Dhaka
  [106.8, -6.2], // Jakarta
  [121.0, 14.6], // Manila
  [151.2, -33.9], // Sydney
  [-77.04, 38.9], // Washington, D.C.
  [-122.42, 37.77], // San Francisco
  [-118.24, 34.05], // Los Angeles
  [-74.0, 40.7], // New York
  [-79.4, 43.7], // Toronto
  [-99.1, 19.4], // Mexico City
  [-46.6, -23.5], // Sao Paulo
  [-77.0, -12.0], // Lima
];

// Matches THREE.SphereGeometry's uv layout so hubs line up with the texture.
function latLon(lon: number, lat: number, r = 1) {
  const phi = ((lon + 180) * Math.PI) / 180;
  const theta = ((90 - lat) * Math.PI) / 180;
  return new THREE.Vector3(
    -r * Math.cos(phi) * Math.sin(theta),
    r * Math.cos(theta),
    r * Math.sin(phi) * Math.sin(theta),
  );
}

const EARTH_VERT = /* glsl */ `
  varying vec2 vUv;
  varying vec3 vN;
  void main() {
    vUv = uv;
    vN = normalize(normalMatrix * normal);
    gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
  }
`;
const EARTH_FRAG = /* glsl */ `
  uniform sampler2D tTopo;
  uniform sampler2D tMask;
  uniform sampler2D tNormal;
  uniform sampler2D tLights;
  uniform vec2 uTexel;
  uniform vec3 uRim;
  uniform vec3 uOrange;
  uniform vec3 uLight;
  uniform float uTime;
  varying vec2 vUv;
  varying vec3 vN;

  void main() {
    vec3 topo = texture2D(tTopo, vUv).rgb;
    float lum = dot(topo, vec3(0.30, 0.55, 0.15));
    float land = smoothstep(0.42, 0.58, 1.0 - texture2D(tMask, vUv).r);

    // relief: slope of the elevation normal map against a north-west light
    vec3 nm = texture2D(tNormal, vUv).xyz * 2.0 - 1.0;
    float slope = dot(nm.xy, normalize(vec2(-0.6, 0.8)));
    float shade = clamp(0.5 + slope * 1.1, 0.0, 1.0);

    // land: three-stop brand ramp from the topo colour (lowland -> upland -> peaks)
    vec3 c0 = vec3(0.03, 0.36, 0.43);
    vec3 c1 = vec3(0.22, 0.66, 0.72);
    vec3 c2 = vec3(0.90, 0.98, 0.98);
    float el = smoothstep(0.10, 0.75, lum);
    vec3 landCol = el < 0.55 ? mix(c0, c1, el / 0.55) : mix(c1, c2, (el - 0.55) / 0.45);
    landCol *= 0.55 + shade * 0.85;

    // ocean: bathymetry from the same map, pale shallows to a deeper teal
    float depth = 1.0 - smoothstep(0.04, 0.42, lum);
    vec3 sea = mix(vec3(0.82, 0.96, 0.98), vec3(0.30, 0.66, 0.77), depth * 0.9);

    // shallow-water halo hugging the coasts
    float coast = 0.0;
    for (int i = 0; i < 8; i++) {
      float a = float(i) * 0.785398;
      vec2 o = vec2(cos(a), sin(a));
      coast += 1.0 - smoothstep(0.42, 0.58, texture2D(tMask, vUv + o * uTexel * 5.0).r);
      coast += 1.0 - smoothstep(0.42, 0.58, texture2D(tMask, vUv + o * uTexel * 11.0).r);
    }
    coast /= 16.0;
    float shallow = clamp(coast * 1.4, 0.0, 1.0) * (1.0 - land);
    sea = mix(sea, vec3(0.55, 0.90, 0.92), shallow * 0.8);

    // slow shimmer on open water
    float wave = sin(vUv.x * 900.0 + uTime * 0.8) * sin(vUv.y * 640.0 - uTime * 0.6);
    sea += wave * 0.012 * (1.0 - land);

    vec3 col = mix(sea, landCol, land);

    // city lights as warm orange specks on land
    float lights = texture2D(tLights, vUv).r;
    col = mix(col, uOrange, smoothstep(0.35, 0.95, lights) * land * 0.7);

    // the light follows the cursor: diffuse falloff, sheen on water, rim light on land
    vec3 N = normalize(vN);
    float diff = dot(N, normalize(uLight));
    col *= 0.78 + 0.34 * diff;
    col += (1.0 - land) * pow(max(diff, 0.0), 12.0) * 0.4;
    col += land * pow(max(diff, 0.0), 6.0) * 0.12;

    // faint graticule every 15 degrees
    vec2 g = vUv * vec2(24.0, 12.0);
    vec2 gl = abs(fract(g - 0.5) - 0.5) / fwidth(g);
    float grid = 1.0 - min(min(gl.x, gl.y), 1.0);
    col = mix(col, vec3(1.0), grid * 0.1);

    // teal rim for the glassy edge
    float f = 1.0 - clamp(vN.z, 0.0, 1.0);
    col = mix(col, uRim, pow(f, 3.2) * 0.65);

    gl_FragColor = vec4(col, 1.0);
  }
`;

const ARC_VERT = /* glsl */ `
  attribute float aT;
  varying float vT;
  varying float vFacing;
  void main() {
    vT = aT;
    vec4 mv = modelViewMatrix * vec4(position, 1.0);
    vFacing = normalize(normalMatrix * normalize(position)).z;
    gl_Position = projectionMatrix * mv;
  }
`;
const ARC_FRAG = /* glsl */ `
  uniform vec3 uColor;
  varying float vT;
  varying float vFacing;
  void main() {
    float env = pow(sin(3.14159 * vT), 0.8);
    float face = smoothstep(-0.3, 0.3, vFacing);
    gl_FragColor = vec4(uColor, env * face * 0.55);
  }
`;
const MOTE_VERT = /* glsl */ `
  attribute float aSize;
  attribute float aMix;
  uniform float uPixel;
  varying float vMix;
  void main() {
    vMix = aMix;
    vec4 mv = modelViewMatrix * vec4(position, 1.0);
    gl_PointSize = aSize * uPixel * (4.0 / -mv.z);
    gl_Position = projectionMatrix * mv;
  }
`;
const MOTE_FRAG = /* glsl */ `
  uniform vec3 uTeal;
  uniform vec3 uOrange;
  varying float vMix;
  void main() {
    float d = length(gl_PointCoord - 0.5);
    if (d > 0.5) discard;
    gl_FragColor = vec4(mix(uTeal, uOrange, vMix), 0.5 * (1.0 - smoothstep(0.0, 0.5, d)));
  }
`;
const HUB_VERT = /* glsl */ `
  attribute float aPhase;
  uniform float uTime;
  uniform float uPixel;
  uniform float uScale;
  varying float vFacing;
  varying float vRing;
  void main() {
    vec4 mv = modelViewMatrix * vec4(position, 1.0);
    vec3 n = normalize(normalMatrix * normalize(position));
    vFacing = n.z;
    vRing = fract(uTime * 0.35 + aPhase);
    gl_PointSize = (10.0 + vRing * 60.0) * uScale * uPixel * (4.0 / -mv.z);
    gl_Position = projectionMatrix * mv;
  }
`;
const HUB_FRAG = /* glsl */ `
  uniform vec3 uColor;
  uniform float uAmount;
  varying float vFacing;
  varying float vRing;
  void main() {
    float d = length(gl_PointCoord - 0.5) * 2.0;
    if (d > 1.0) discard;
    float face = smoothstep(-0.05, 0.4, vFacing);
    // solid core + an expanding ring that fades out
    float core = (1.0 - smoothstep(0.12, 0.22, d)) * 0.95;
    float ringR = 0.35 + vRing * 0.6;
    float ring = (1.0 - smoothstep(0.0, 0.09, abs(d - ringR))) * (1.0 - vRing) * 0.9;
    gl_FragColor = vec4(uColor, max(core, ring) * face * uAmount);
  }
`;
const STREAM_VERT = /* glsl */ `
  attribute float aFade;
  uniform float uPixel;
  varying float vFacing;
  varying float vFade;
  void main() {
    vec4 mv = modelViewMatrix * vec4(position, 1.0);
    vec3 n = normalize(normalMatrix * normalize(position));
    vFacing = n.z;
    vFade = aFade;
    gl_PointSize = (7.0 + aFade * 9.0) * uPixel * (4.0 / -mv.z);
    gl_Position = projectionMatrix * mv;
  }
`;
const STREAM_FRAG = /* glsl */ `
  uniform vec3 uColor;
  varying float vFacing;
  varying float vFade;
  void main() {
    float d = length(gl_PointCoord - 0.5);
    if (d > 0.5) discard;
    float face = smoothstep(-0.3, 0.3, vFacing);
    gl_FragColor = vec4(uColor, vFade * face * (1.0 - smoothstep(0.05, 0.5, d)));
  }
`;
const GLOW_VERT = /* glsl */ `
  varying vec3 vN;
  void main() {
    vN = normalize(normalMatrix * normal);
    gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
  }
`;
const GLOW_FRAG = /* glsl */ `
  uniform vec3 uRim;
  uniform vec3 uWarm;
  varying vec3 vN;
  void main() {
    // BackSide: s runs 0 (centre) to 1 (outer silhouette). The globe's own edge sits at
    // s = 1/1.2 = 0.83, so the halo peaks there and fades to nothing at the outer edge.
    float s = sqrt(max(0.0, 1.0 - vN.z * vN.z));
    float f = (1.0 - smoothstep(0.83, 1.0, s)) * smoothstep(0.79, 0.85, s);
    // teal all round, drifting to orange toward the lower left like a sunrise
    float warm = smoothstep(0.1, 0.9, -vN.x * 0.55 - vN.y * 0.75 + 0.2);
    gl_FragColor = vec4(mix(uRim, uWarm, warm * 0.8), f * 0.55);
  }
`;

// Cinematic timing (ms). The globe turns first, then the camera falls in; leaving is quicker.
const TURN_MS = 2600;
const DIVE_MS = 6200;
const LEAVE_TURN_MS = 1600;
const LEAVE_DIVE_MS = 2000;
const BASE_FOV = 35;
const FOV_TELE = 8; // the lens tightens by this much at the bottom of the dive

const clamp01 = (v: number) => Math.max(0, Math.min(1, v));
const easeInOutCubic = (t: number) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
const easeInOutQuart = (t: number) => (t < 0.5 ? 8 * t * t * t * t : 1 - Math.pow(-2 * t + 2, 4) / 2);

export interface GlobeFocus {
  lon: number;
  lat: number;
  label: string;
  /** If set, the camera dives into the place and opens onto this photo. */
  image?: string;
  credit?: string;
  /** Where the photo's subject sits horizontally, 0..1 across the image (default centre). */
  imageFocusX?: number;
}

/**
 * `focus` turns the globe to a place and pins it; with an `image` the camera then
 * dives in and a photo opens out of the pin like a portal. null reverses all of it.
 */
export default function InteractiveBackground({ focus = null }: { focus?: GlobeFocus | null }) {
  const mountRef = useRef<HTMLDivElement>(null);
  const focusRef = useRef<GlobeFocus | null>(focus);
  focusRef.current = focus;

  useEffect(() => {
    const mount = mountRef.current;
    if (!mount) return;

    const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    const pixel = Math.min(window.devicePixelRatio || 1, 2);
    renderer.setPixelRatio(pixel);
    renderer.setClearColor(0x000000, 0);
    mount.appendChild(renderer.domElement);
    renderer.domElement.style.display = "block";

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(35, 1, 0.1, 50);
    const CAM_Z = 6;
    camera.position.z = CAM_Z;

    const globe = new THREE.Group();
    scene.add(globe);

    const disposables: { dispose: () => void }[] = [];
    const track = <T extends { dispose: () => void }>(o: T) => (disposables.push(o), o);

    // Earth: textures load async; the globe fades in once they are all ready.
    const TEXTURES = 5;
    let loaded = 0;
    mount.style.opacity = "0";
    mount.style.transition = "opacity 1.4s ease";
    const loader = new THREE.TextureLoader();
    const load = (url: string) => {
      const t = track(
        loader.load(url, () => {
          if (++loaded === TEXTURES) mount.style.opacity = "1";
          renderOnce();
        }),
      );
      t.anisotropy = renderer.capabilities.getMaxAnisotropy();
      return t;
    };
    const earthMat = track(
      new THREE.ShaderMaterial({
        vertexShader: EARTH_VERT,
        fragmentShader: EARTH_FRAG,
        uniforms: {
          tTopo: { value: load("/earth/topo.jpg") },
          tMask: { value: load("/earth/earth_specular_2048.jpg") },
          tNormal: { value: load("/earth/earth_normal_2048.jpg") },
          tLights: { value: load("/earth/earth_lights_2048.png") },
          uTexel: { value: new THREE.Vector2(1 / 2048, 1 / 1024) },
          uRim: { value: TEAL },
          uOrange: { value: ORANGE },
          uLight: { value: new THREE.Vector3(-0.45, 0.5, 0.75).normalize() },
          uTime: { value: 0 },
        },
      }),
    );
    const sphereGeo = track(new THREE.SphereGeometry(1, 96, 96));
    globe.add(new THREE.Mesh(sphereGeo, earthMat));

    const glowMat = track(
      new THREE.ShaderMaterial({
        vertexShader: GLOW_VERT,
        fragmentShader: GLOW_FRAG,
        uniforms: { uRim: { value: TEAL }, uWarm: { value: ORANGE } },
        transparent: true,
        side: THREE.BackSide,
        depthWrite: false,
      }),
    );
    const glow = new THREE.Mesh(sphereGeo, glowMat);
    glow.scale.setScalar(1.2);
    glow.renderOrder = -1;
    globe.add(glow);

    // drifting cloud layer, a hair above the surface
    const cloudMat = track(
      new THREE.MeshBasicMaterial({
        map: load("/earth/earth_clouds_1024.png"),
        transparent: true,
        opacity: 0.3,
        depthWrite: false,
      }),
    );
    const clouds = new THREE.Mesh(sphereGeo, cloudMat);
    clouds.scale.setScalar(1.012);
    globe.add(clouds);

    // country borders (Natural Earth 50m, land boundaries), fetched after first paint
    const borderMat = track(new THREE.LineBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.45, depthWrite: false }));
    const borderGeos: THREE.BufferGeometry[] = [];
    let cancelled = false;
    fetch("/earth/borders.json")
      .then((r) => r.json())
      .then((lines: number[][]) => {
        if (cancelled) return;
        const pts: THREE.Vector3[] = [];
        for (const line of lines) {
          for (let i = 0; i + 3 < line.length; i += 2) {
            pts.push(latLon(line[i], line[i + 1], 1.003), latLon(line[i + 2], line[i + 3], 1.003));
          }
        }
        const geo = new THREE.BufferGeometry().setFromPoints(pts);
        borderGeos.push(geo);
        globe.add(new THREE.LineSegments(geo, borderMat));
        renderOnce();
      })
      .catch(() => {});

    // hubs on real cities
    const hubs = CITIES.map(([lon, lat]) => latLon(lon, lat));
    const hubGeo = track(new THREE.BufferGeometry().setFromPoints(hubs.map((h) => h.clone().multiplyScalar(1.004))));
    hubGeo.setAttribute("aPhase", new THREE.BufferAttribute(new Float32Array(hubs.map(() => Math.random())), 1));
    const hubMat = track(
      new THREE.ShaderMaterial({
        vertexShader: HUB_VERT,
        fragmentShader: HUB_FRAG,
        uniforms: {
          uColor: { value: ORANGE },
          uTime: { value: 0 },
          uPixel: { value: pixel },
          uScale: { value: 1 },
          uAmount: { value: 1 },
        },
        transparent: true,
        depthWrite: false,
      }),
    );
    globe.add(new THREE.Points(hubGeo, hubMat));

    // focus pin: one bigger, brighter hub that follows the current focus
    const pinGeo = track(new THREE.BufferGeometry().setFromPoints([new THREE.Vector3(0, 0, 1.006)]));
    pinGeo.setAttribute("aPhase", new THREE.BufferAttribute(new Float32Array([0]), 1));
    const pinMat = track(hubMat.clone());
    pinMat.uniforms = {
      uColor: { value: ORANGE },
      uTime: { value: 0 },
      uPixel: { value: pixel },
      uScale: { value: 2.1 },
      uAmount: { value: 0 },
    };
    const pin = new THREE.Points(pinGeo, pinMat);
    pin.frustumCulled = false;
    globe.add(pin);

    const label = document.createElement("div");
    Object.assign(label.style, {
      position: "absolute",
      left: "0",
      top: "0",
      padding: "6px 12px",
      borderRadius: "999px",
      background: "rgba(255,255,255,0.92)",
      color: "#087f93",
      font: "700 13px 'Segoe UI', Tahoma, sans-serif",
      letterSpacing: "0.02em",
      whiteSpace: "nowrap",
      boxShadow: "0 6px 20px rgba(8,52,60,0.18)",
      opacity: "0",
      willChange: "transform, opacity",
    });
    mount.appendChild(label);

    // vignette closes in during the fall; bloom flares as the camera breaks through to the photo
    const vignette = document.createElement("div");
    Object.assign(vignette.style, {
      position: "absolute",
      inset: "0",
      background: "radial-gradient(ellipse at 50% 50%, rgba(5,46,53,0) 35%, rgba(5,46,53,0.6) 100%)",
      opacity: "0",
    });
    mount.appendChild(vignette);

    // the place photo: opens out of the pin as a circle, then settles with a slow Ken Burns drift
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
        "radial-gradient(ellipse at 50% 45%, rgba(255,255,255,0) 40%, rgba(5,46,53,0.35) 100%), linear-gradient(180deg, rgba(255,255,255,0.0) 55%, rgba(5,46,53,0.28) 100%)",
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
    let photoFor: GlobeFocus | null = null;
    let photoFocusX = 0.5;

    // arcs between nearby hubs, with streams of pulses riding them
    interface Arc { curve: THREE.QuadraticBezierCurve3; offset: number; speed: number }
    const arcs: Arc[] = [];
    const arcLines: THREE.Vector3[] = [];
    const arcT: number[] = [];
    const seen = new Set<string>();
    for (let i = 0; i < hubs.length; i++) {
      const nearest = hubs
        .map((h, j) => ({ j, d: h.distanceTo(hubs[i]) }))
        .filter((o) => o.j !== i)
        .sort((a, b) => a.d - b.d)
        .slice(0, 2);
      for (const { j, d } of nearest) {
        const key = i < j ? `${i}-${j}` : `${j}-${i}`;
        if (seen.has(key)) continue;
        seen.add(key);
        const mid = hubs[i].clone().add(hubs[j]).multiplyScalar(0.5).normalize().multiplyScalar(1 + d * 0.45);
        const curve = new THREE.QuadraticBezierCurve3(hubs[i], mid, hubs[j]);
        arcs.push({ curve, offset: Math.random(), speed: 0.00006 + Math.random() * 0.00005 });
        const pts = curve.getPoints(40);
        for (let k = 0; k < pts.length - 1; k++) {
          arcLines.push(pts[k], pts[k + 1]);
          arcT.push(k / (pts.length - 1), (k + 1) / (pts.length - 1));
        }
      }
    }
    const lineGeo = track(new THREE.BufferGeometry().setFromPoints(arcLines));
    lineGeo.setAttribute("aT", new THREE.BufferAttribute(new Float32Array(arcT), 1));
    const lineMat = track(
      new THREE.ShaderMaterial({
        vertexShader: ARC_VERT,
        fragmentShader: ARC_FRAG,
        uniforms: { uColor: { value: ORANGE } },
        transparent: true,
        depthWrite: false,
      }),
    );
    globe.add(new THREE.LineSegments(lineGeo, lineMat));

    const comets = 2; // heads per arc
    const tail = 7; // dots behind each head
    const perArc = comets * tail;
    const streamCount = arcs.length * perArc;
    const streamPos = new Float32Array(streamCount * 3);
    const streamFade = new Float32Array(streamCount);
    const streamGeo = track(new THREE.BufferGeometry());
    streamGeo.setAttribute("position", new THREE.BufferAttribute(streamPos, 3));
    streamGeo.setAttribute("aFade", new THREE.BufferAttribute(streamFade, 1));
    const streamMat = track(
      new THREE.ShaderMaterial({
        vertexShader: STREAM_VERT,
        fragmentShader: STREAM_FRAG,
        uniforms: { uColor: { value: ORANGE }, uPixel: { value: pixel } },
        transparent: true,
        depthWrite: false,
      }),
    );
    const streams = new THREE.Points(streamGeo, streamMat);
    streams.frustumCulled = false;
    globe.add(streams);

    const tmp = new THREE.Vector3();
    const updateStreams = (now: number) => {
      arcs.forEach((arc, a) => {
        for (let c = 0; c < comets; c++) {
          const head = arc.offset + now * arc.speed + c / comets;
          for (let k = 0; k < tail; k++) {
            const t = (((head - k * 0.014) % 1) + 1) % 1;
            arc.curve.getPoint(t, tmp);
            const idx = a * perArc + c * tail + k;
            streamPos.set([tmp.x, tmp.y, tmp.z], idx * 3);
            // fades in and out along the arc, and thins out along the tail
            streamFade[idx] = Math.sin(t * Math.PI) * (1 - k / tail);
          }
        }
      });
      streamGeo.attributes.position.needsUpdate = true;
      streamGeo.attributes.aFade.needsUpdate = true;
    };

    // drifting motes in a shell around the globe, for depth
    const moteCount = 110;
    const motePos = new Float32Array(moteCount * 3);
    const moteSize = new Float32Array(moteCount);
    const moteMix = new Float32Array(moteCount);
    for (let i = 0; i < moteCount; i++) {
      const dir = new THREE.Vector3(Math.random() - 0.5, Math.random() - 0.5, Math.random() - 0.5).normalize();
      dir.multiplyScalar(1.5 + Math.random() * 1.4);
      motePos.set([dir.x, dir.y, dir.z], i * 3);
      moteSize[i] = 3 + Math.random() * 7;
      moteMix[i] = Math.random() < 0.2 ? 1 : 0;
    }
    const moteGeo = track(new THREE.BufferGeometry());
    moteGeo.setAttribute("position", new THREE.BufferAttribute(motePos, 3));
    moteGeo.setAttribute("aSize", new THREE.BufferAttribute(moteSize, 1));
    moteGeo.setAttribute("aMix", new THREE.BufferAttribute(moteMix, 1));
    const moteMat = track(
      new THREE.ShaderMaterial({
        vertexShader: MOTE_VERT,
        fragmentShader: MOTE_FRAG,
        uniforms: { uTeal: { value: TEAL }, uOrange: { value: ORANGE }, uPixel: { value: pixel } },
        transparent: true,
        depthWrite: false,
      }),
    );
    const motes = new THREE.Points(moteGeo, moteMat);
    motes.frustumCulled = false;
    globe.add(motes);

    // layout
    let w = 1;
    let h = 1;
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
      const visibleH = 2 * CAM_Z * Math.tan((BASE_FOV * Math.PI) / 360);
      const visibleW = visibleH * camera.aspect;
      // big enough to bleed off the page, but never wider than the viewport allows
      const baseScale = Math.min(visibleH * 0.62, visibleW * 0.55);
      globe.userData.baseScale = baseScale;
      globe.scale.setScalar(baseScale * (1 + amount * 0.1));
      radiusPx = (baseScale / visibleH) * h;
      layoutPhoto();
      if (reduced) renderOnce();
    };

    const mouse = { x: 0, y: 0 };
    const cur = { x: 0, y: 0 };
    const onMove = (e: PointerEvent) => {
      mouse.x = (e.clientX / w) * 2 - 1;
      mouse.y = (e.clientY / h) * 2 - 1;
    };

    // start with the Levant facing the camera
    const home = latLon(36, 28);
    const baseY = -Math.atan2(home.x, home.z);
    const baseX = 0.45;
    let spin = 0;
    let rotY = baseY;
    let rotX = baseX;
    let amount = 0; // eased 0..1: free drift -> turned to the focus
    let amountT = 0; // linear progress behind `amount`
    let diveT = 0; // linear 0..1: looking at the globe -> inside the place photo
    let held = 0; // ms spent fully inside the photo, for the slow settle
    let lastRender = performance.now();
    let shownFocus: GlobeFocus | null = null;
    let pinFor: GlobeFocus | null = null;
    let radiusPx = 600;
    const wrapPi = (a: number) => Math.atan2(Math.sin(a), Math.cos(a));
    const ndc = new THREE.Vector3();
    let raf = 0;
    let last = performance.now();

    function renderOnce(now = performance.now()) {
      // ease toward the focused place (or back to the drifting view)
      const f = focusRef.current;
      if (f) shownFocus = f;
      const dtMs = Math.min(now - lastRender, 60);
      lastRender = now;
      amountT = clamp01(amountT + (f ? dtMs / TURN_MS : -dtMs / LEAVE_TURN_MS));
      amount = easeInOutCubic(amountT);
      // the fall starts once the globe has finished turning; leaving unwinds it faster
      const wantDive = !!(f && f.image && amountT >= 1);
      diveT = clamp01(diveT + (wantDive ? dtMs / DIVE_MS : -dtMs / LEAVE_DIVE_MS));
      const zoom = easeInOutQuart(diveT);
      let targetY = baseY + spin;
      let targetX = baseX;
      if (shownFocus) {
        const pt = latLon(shownFocus.lon, shownFocus.lat);
        // park the place beside the sign-in card instead of behind it
        const psi = Math.asin(Math.min(0.8, Math.max(0.25, 360 / radiusPx))) * (1 - zoom);
        const fy = psi - Math.atan2(pt.x, pt.z);
        const fx = (shownFocus.lat * Math.PI) / 180;
        targetY = baseY + spin + wrapPi(fy - (baseY + spin)) * amount;
        targetX = baseX + (fx - baseX) * amount;
        if (pinFor !== shownFocus) {
          pinFor = shownFocus;
          const q = pt.clone().multiplyScalar(1.006);
          const pos = pinGeo.attributes.position as THREE.BufferAttribute;
          pos.setXYZ(0, q.x, q.y, q.z);
          pos.needsUpdate = true;
        }
      }
      rotY += wrapPi(targetY - rotY) * 0.08;
      rotX += (targetX - rotX) * 0.08;
      const cursor = 1 - amount * 0.8;
      globe.rotation.y = rotY + cur.x * 0.7 * cursor;
      globe.rotation.x = rotX + cur.y * 0.35 * cursor;
      globe.scale.setScalar((globe.userData.baseScale ?? 1) * (1 + amount * 0.1));
      pinMat.uniforms.uAmount.value = amount * (1 - Math.min(1, zoom * 2));
      // camera dolly toward the pin; the surface sits at globe scale in front of the origin
      const sNow = globe.scale.x;
      camera.position.z = CAM_Z - zoom * (CAM_Z - (sNow + 0.55));
      pinMat.uniforms.uTime.value = now * 0.0012;
      camera.position.x = cur.x * 0.18 * (1 - zoom);
      camera.position.y = -cur.y * 0.12 * (1 - zoom);
      // a gentle bank that peaks mid-fall, and a lens that tightens toward the end
      const bank = Math.sin(zoom * Math.PI) * 0.07;
      camera.up.set(Math.sin(bank), Math.cos(bank), 0);
      const fov = BASE_FOV - zoom * FOV_TELE;
      if (camera.fov !== fov) {
        camera.fov = fov;
        camera.updateProjectionMatrix();
      }
      camera.lookAt(0, 0, 0);
      hubMat.uniforms.uTime.value = now * 0.001;
      earthMat.uniforms.uTime.value = now * 0.001;
      clouds.rotation.y = now * 0.00001;
      earthMat.uniforms.uLight.value.set(-0.45 + cur.x * 0.95, 0.5 - cur.y * 0.8, 0.75).normalize();
      updateStreams(now);
      if (zoom < 0.995) renderer.render(scene, camera);

      vignette.style.opacity = String(Math.sin(Math.min(1, zoom * 1.15) * Math.PI) * 0.9);

      if (shownFocus && shownFocus.image) {
        if (photoFor !== shownFocus) {
          photoFor = shownFocus;
          photoFocusX = shownFocus.imageFocusX ?? 0.5;
          layoutPhoto();
          photoImg.style.backgroundImage = `url(${shownFocus.image})`;
          credit.textContent = shownFocus.credit ?? "";
        }
        const reveal = clamp01((zoom - 0.55) / 0.43);
        const eased = reveal * reveal * (3 - 2 * reveal);
        photo.style.opacity = String(Math.min(1, eased * 2));
        photo.style.clipPath = `circle(${eased * 150}% at 50% 50%)`;
        // Ken Burns: starts pushed in and settles; the cursor shifts it a little
        held = eased > 0.99 ? held + dtMs : 0;
        // Ken Burns: starts pushed in, settles as the fall ends, then keeps creeping in very slowly
        const creep = Math.min(0.06, held * 0.000006);
        photoImg.style.transform = `translate(${-cur.x * 14}px, ${-cur.y * 10}px) scale(${1 + (1 - eased) * 0.28 + creep})`;
        bloom.style.opacity = String(Math.sin(reveal * Math.PI) * 0.6);
        credit.style.opacity = String(Math.max(0, (eased - 0.6) * 2.5));
      } else {
        photo.style.opacity = "0";
        credit.style.opacity = "0";
        bloom.style.opacity = "0";
      }

      if (shownFocus) {
        ndc.copy(latLon(shownFocus.lon, shownFocus.lat)).multiplyScalar(1.006);
        globe.updateMatrixWorld();
        ndc.applyMatrix4(globe.matrixWorld);
        const facing = ndc.clone().normalize().dot(camera.position.clone().sub(ndc).normalize());
        ndc.project(camera);
        const x = ((ndc.x + 1) / 2) * w;
        const y = ((1 - ndc.y) / 2) * h;
        label.textContent = shownFocus.label;
        label.style.transform = `translate(${x + 16}px, ${y - 38}px)`;
        label.style.opacity = String(Math.max(0, Math.min(1, (amount - 0.6) * 2.5)) * (facing > 0 ? 1 : 0) * Math.max(0, 1 - zoom * 3));
      } else {
        label.style.opacity = "0";
      }
    }

    const frame = (now: number) => {
      const dt = Math.min(now - last, 50);
      last = now;
      spin += dt * 0.00006;
      cur.x += (mouse.x - cur.x) * 0.045;
      cur.y += (mouse.y - cur.y) * 0.045;
      renderOnce(now);
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
    if (reduced) renderOnce();
    else raf = requestAnimationFrame(frame);

    return () => {
      cancelAnimationFrame(raf);
      window.removeEventListener("resize", resize);
      window.removeEventListener("pointermove", onMove);
      document.removeEventListener("visibilitychange", onVisibility);
      disposables.forEach((d) => d.dispose());
      cancelled = true;
      label.remove();
      photo.remove();
      vignette.remove();
      bloom.remove();
      credit.remove();
      borderGeos.forEach((g) => g.dispose());
      renderer.dispose();
      renderer.domElement.remove();
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
