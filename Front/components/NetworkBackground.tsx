"use client";

import { useEffect, useRef } from "react";

/**
 * A luminous 3D network of nodes (readers, writers, nonprofits) joined by hairline links, drawn on a
 * canvas that fills its parent. The field drifts on its own and tilts in perspective with the
 * cursor; a soft light follows the pointer and brightens whatever it passes. Orange nodes are
 * nonprofits, and comet-like orange pulses travel the links toward them: funding moving through
 * the network.
 *
 * Plain canvas, no dependencies. Pauses when the tab is hidden and renders a single static frame
 * for reduced-motion users.
 */

interface Node {
  x: number; // world space, x -1.6..1.6, y -1..1, z -1..1
  y: number;
  z: number;
  vx: number;
  vy: number;
  vz: number;
  orange: boolean;
  phase: number;
}

interface Pulse {
  a: number;
  b: number;
  t: number;
  speed: number;
}

interface Proj {
  sx: number;
  sy: number;
  p: number; // perspective scale
  d: number; // 0 near .. 1 far
}

const TEAL = "17, 160, 182";
const ORANGE = "255, 122, 0";
const LINK_DIST = 0.85;
const FOCAL = 1.6;

export default function NetworkBackground({ className }: { className?: string }) {
  const wrapRef = useRef<HTMLDivElement>(null);
  const canvasRef = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const wrap = wrapRef.current;
    const canvas = canvasRef.current;
    const ctx = canvas?.getContext("2d");
    if (!wrap || !canvas || !ctx) return;

    const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    let w = 0;
    let h = 0;
    let dpr = 1;
    let nodes: Node[] = [];
    let count = 0;
    const pulses: Pulse[] = [];

    const build = () => {
      count = Math.round(Math.min(90, Math.max(30, (w * h) / 12000)));
      nodes = Array.from({ length: count }, () => ({
        x: (Math.random() * 2 - 1) * 1.6,
        y: (Math.random() * 2 - 1) * 1.0,
        z: Math.random() * 2 - 1,
        vx: (Math.random() - 0.5) * 0.0005,
        vy: (Math.random() - 0.5) * 0.0005,
        vz: (Math.random() - 0.5) * 0.0005,
        orange: Math.random() < 0.16,
        phase: Math.random() * Math.PI * 2,
      }));
      pulses.length = 0;
    };

    const resize = () => {
      dpr = Math.min(window.devicePixelRatio || 1, 2);
      w = wrap.clientWidth;
      h = wrap.clientHeight;
      canvas.width = Math.max(1, Math.round(w * dpr));
      canvas.height = Math.max(1, Math.round(h * dpr));
      canvas.style.width = `${w}px`;
      canvas.style.height = `${h}px`;
      build();
      if (reduced) frame(performance.now());
    };

    // mouse in px relative to this panel, smoothed into `cur`
    const mouse = { x: 0, y: 0 };
    const cur = { x: 0, y: 0 };
    const onMove = (e: PointerEvent) => {
      const r = wrap.getBoundingClientRect();
      mouse.x = e.clientX - r.left;
      mouse.y = e.clientY - r.top;
    };

    let raf = 0;
    let last = performance.now();
    let spawnAt = 0;
    let drift = 0;

    function frame(now: number) {
      const dt = Math.min(now - last, 50);
      last = now;
      if (!reduced) drift += dt * 0.00006;
      cur.x += (mouse.x - cur.x) * 0.05;
      cur.y += (mouse.y - cur.y) * 0.05;
      const nx = (cur.x - w / 2) / (w / 2); // -1..1
      const ny = (cur.y - h / 2) / (h / 2);

      // field rotation: slow idle sway plus cursor tilt
      const ry = nx * 0.38 + Math.sin(drift * 6) * 0.12;
      const rx = -ny * 0.24 + Math.cos(drift * 5) * 0.05;
      const cy = Math.cos(ry), sy = Math.sin(ry);
      const cx = Math.cos(rx), sx = Math.sin(rx);
      const scale = Math.min(w * 0.42, h * 0.55);

      const proj: Proj[] = nodes.map((n) => {
        if (!reduced) {
          n.x += n.vx * dt;
          n.y += n.vy * dt;
          n.z += n.vz * dt;
          if (Math.abs(n.x) > 1.6) n.vx *= -1;
          if (Math.abs(n.y) > 1.0) n.vy *= -1;
          if (Math.abs(n.z) > 1) n.vz *= -1;
        }
        const x1 = n.x * cy + n.z * sy;
        const z1 = -n.x * sy + n.z * cy;
        const y2 = n.y * cx - z1 * sx;
        const z2 = n.y * sx + z1 * cx;
        const p = FOCAL / (FOCAL + z2 + 1.2);
        return { sx: w / 2 + x1 * scale * p, sy: h / 2 + y2 * scale * p, p, d: (z2 + 1) / 2 };
      });

      ctx!.setTransform(dpr, 0, 0, dpr, 0, 0);
      ctx!.clearRect(0, 0, w, h);

      // soft light that follows the cursor
      const light = ctx!.createRadialGradient(cur.x, cur.y, 0, cur.x, cur.y, 320);
      light.addColorStop(0, `rgba(${TEAL}, 0.13)`);
      light.addColorStop(0.5, `rgba(${TEAL}, 0.05)`);
      light.addColorStop(1, `rgba(${TEAL}, 0)`);
      ctx!.fillStyle = light;
      ctx!.fillRect(0, 0, w, h);

      // links: teal, blending to orange where a nonprofit sits on one end
      ctx!.lineWidth = 1;
      for (let i = 0; i < count; i++) {
        for (let j = i + 1; j < count; j++) {
          const a = nodes[i], b = nodes[j];
          const dx = a.x - b.x, dy = a.y - b.y, dz = a.z - b.z;
          const dist = Math.sqrt(dx * dx + dy * dy + dz * dz);
          if (dist > LINK_DIST) continue;
          const pa = proj[i], pb = proj[j];
          const mx = (pa.sx + pb.sx) / 2 - cur.x;
          const my = (pa.sy + pb.sy) / 2 - cur.y;
          const glow = Math.max(0, 1 - Math.hypot(mx, my) / 300);
          const fade = 1 - dist / LINK_DIST;
          const alpha = (0.15 + glow * 0.5) * fade * (0.5 + (pa.p + pb.p) * 0.35);
          if (a.orange !== b.orange) {
            const g = ctx!.createLinearGradient(pa.sx, pa.sy, pb.sx, pb.sy);
            g.addColorStop(0, `rgba(${a.orange ? ORANGE : TEAL}, ${alpha})`);
            g.addColorStop(1, `rgba(${b.orange ? ORANGE : TEAL}, ${alpha})`);
            ctx!.strokeStyle = g;
          } else {
            ctx!.strokeStyle = `rgba(${a.orange ? ORANGE : TEAL}, ${alpha})`;
          }
          ctx!.beginPath();
          ctx!.moveTo(pa.sx, pa.sy);
          ctx!.lineTo(pb.sx, pb.sy);
          ctx!.stroke();
        }
      }

      // funding pulses: a short comet along a link, heading to a nonprofit
      if (!reduced) {
        if (now > spawnAt && pulses.length < 7) {
          const orangeIdx: number[] = [];
          nodes.forEach((n, i) => n.orange && orangeIdx.push(i));
          const target = orangeIdx[Math.floor(Math.random() * orangeIdx.length)];
          if (target !== undefined) {
            const near: number[] = [];
            for (let j = 0; j < count; j++) {
              if (j === target) continue;
              const d = Math.hypot(nodes[target].x - nodes[j].x, nodes[target].y - nodes[j].y, nodes[target].z - nodes[j].z);
              if (d < LINK_DIST) near.push(j);
            }
            if (near.length) {
              pulses.push({
                a: near[Math.floor(Math.random() * near.length)],
                b: target,
                t: 0,
                speed: 0.0005 + Math.random() * 0.0004,
              });
            }
          }
          spawnAt = now + 400 + Math.random() * 800;
        }
        for (let k = pulses.length - 1; k >= 0; k--) {
          const pl = pulses[k];
          pl.t += pl.speed * dt;
          if (pl.t >= 1) { pulses.splice(k, 1); continue; }
          const pa = proj[pl.a], pb = proj[pl.b];
          const at = (t: number) => ({ x: pa.sx + (pb.sx - pa.sx) * t, y: pa.sy + (pb.sy - pa.sy) * t });
          const head = at(pl.t);
          const tail = at(Math.max(0, pl.t - 0.18));
          const tg = ctx!.createLinearGradient(tail.x, tail.y, head.x, head.y);
          tg.addColorStop(0, `rgba(${ORANGE}, 0)`);
          tg.addColorStop(1, `rgba(${ORANGE}, 0.85)`);
          ctx!.strokeStyle = tg;
          ctx!.lineWidth = 2;
          ctx!.beginPath();
          ctx!.moveTo(tail.x, tail.y);
          ctx!.lineTo(head.x, head.y);
          ctx!.stroke();
          const hg = ctx!.createRadialGradient(head.x, head.y, 0, head.x, head.y, 9);
          hg.addColorStop(0, `rgba(${ORANGE}, 0.95)`);
          hg.addColorStop(1, `rgba(${ORANGE}, 0)`);
          ctx!.fillStyle = hg;
          ctx!.beginPath();
          ctx!.arc(head.x, head.y, 9, 0, Math.PI * 2);
          ctx!.fill();
        }
        ctx!.lineWidth = 1;
      }

      // nodes: far ones are small and faint, near ones are crisp with a halo;
      // anything the cursor light touches swells slightly
      const order = proj.map((_, i) => i).sort((i, j) => proj[j].d - proj[i].d);
      for (const i of order) {
        const n = nodes[i], p = proj[i];
        const lit = Math.max(0, 1 - Math.hypot(p.sx - cur.x, p.sy - cur.y) / 260);
        const breathe = 1 + Math.sin(now * 0.0016 + n.phase) * 0.12;
        const r = (n.orange ? 3.4 : 2.3) * p.p * 1.4 * breathe * (1 + lit * 0.7);
        const alpha = Math.min(1, 0.3 + (1 - p.d) * 0.5 + lit * 0.3);
        const col = n.orange ? ORANGE : TEAL;

        const halo = ctx!.createRadialGradient(p.sx, p.sy, 0, p.sx, p.sy, r * 5);
        halo.addColorStop(0, `rgba(${col}, ${alpha * 0.32})`);
        halo.addColorStop(1, `rgba(${col}, 0)`);
        ctx!.fillStyle = halo;
        ctx!.beginPath();
        ctx!.arc(p.sx, p.sy, r * 5, 0, Math.PI * 2);
        ctx!.fill();

        ctx!.fillStyle = `rgba(${col}, ${alpha})`;
        ctx!.beginPath();
        ctx!.arc(p.sx, p.sy, r, 0, Math.PI * 2);
        ctx!.fill();
        if (n.orange) {
          ctx!.strokeStyle = `rgba(${ORANGE}, ${alpha * 0.5})`;
          ctx!.beginPath();
          ctx!.arc(p.sx, p.sy, r + 4 * p.p + Math.sin(now * 0.002 + n.phase) * 1.2, 0, Math.PI * 2);
          ctx!.stroke();
        }
      }

      if (!reduced) raf = requestAnimationFrame(frame);
    }

    const ro = new ResizeObserver(resize);
    ro.observe(wrap);
    window.addEventListener("pointermove", onMove, { passive: true });
    mouse.x = cur.x = wrap.clientWidth / 2;
    mouse.y = cur.y = wrap.clientHeight / 2;
    resize();

    const onVisibility = () => {
      if (reduced) return;
      cancelAnimationFrame(raf);
      if (!document.hidden) {
        last = performance.now();
        raf = requestAnimationFrame(frame);
      }
    };
    document.addEventListener("visibilitychange", onVisibility);
    if (!reduced) raf = requestAnimationFrame(frame);

    return () => {
      cancelAnimationFrame(raf);
      ro.disconnect();
      window.removeEventListener("pointermove", onMove);
      document.removeEventListener("visibilitychange", onVisibility);
    };
  }, []);

  return (
    <div ref={wrapRef} className={className} aria-hidden="true">
      <canvas ref={canvasRef} style={{ display: "block" }} />
    </div>
  );
}
