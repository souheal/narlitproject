/**
 * The organization dashboard banner's centrepiece: an isometric headquarters
 * with a published story floating beside it and donations gathering at its
 * base — the organization's stories turning into support.
 *
 * Same visual language as ImpactScene, GrowthScene and DiscoverScene
 * (isometric slabs, rim light on the lit edges, soft contact shadows, brand
 * gradients). Motion lives in globals.css and is dropped for reduced-motion users.
 */
export default function OrgScene({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 420 360"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
      focusable="false"
    >
      <defs>
        <linearGradient id="os-top" x1="0.1" y1="0" x2="0.9" y2="1">
          <stop offset="0%" stopColor="#7deff8" />
          <stop offset="50%" stopColor="#2ac6d8" />
          <stop offset="100%" stopColor="#0f9cad" />
        </linearGradient>
        <linearGradient id="os-left" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#0b8496" />
          <stop offset="100%" stopColor="#04505d" />
        </linearGradient>
        <linearGradient id="os-right" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#14a7b8" />
          <stop offset="100%" stopColor="#076f80" />
        </linearGradient>
        <linearGradient id="os-window" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#ffe3a8" />
          <stop offset="100%" stopColor="#ff9c33" />
        </linearGradient>
        <linearGradient id="os-window-dim" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#bff6fb" stopOpacity="0.55" />
          <stop offset="100%" stopColor="#7deff8" stopOpacity="0.25" />
        </linearGradient>

        <linearGradient id="os-page" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#ffffff" />
          <stop offset="100%" stopColor="#e3eef0" />
        </linearGradient>
        <linearGradient id="os-page-edge" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#c3d6d9" />
          <stop offset="100%" stopColor="#8fb0b5" />
        </linearGradient>
        <linearGradient id="os-accent" x1="0" y1="0" x2="1" y2="0">
          <stop offset="0%" stopColor="#ff9c33" />
          <stop offset="100%" stopColor="#f07500" />
        </linearGradient>
        <linearGradient id="os-heart" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#ffb347" />
          <stop offset="100%" stopColor="#ff7a00" />
        </linearGradient>

        <linearGradient id="os-coin" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#ffe3a8" />
          <stop offset="100%" stopColor="#ff9a1f" />
        </linearGradient>
        <linearGradient id="os-coin-side" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#f0900f" />
          <stop offset="100%" stopColor="#c46a00" />
        </linearGradient>

        <radialGradient id="os-glow" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#11b6c8" stopOpacity="0.40" />
          <stop offset="55%" stopColor="#11b6c8" stopOpacity="0.10" />
          <stop offset="100%" stopColor="#11b6c8" stopOpacity="0" />
        </radialGradient>
        <radialGradient id="os-glow-warm" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#ff7a00" stopOpacity="0.30" />
          <stop offset="100%" stopColor="#ff7a00" stopOpacity="0" />
        </radialGradient>
        <radialGradient id="os-shadow" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#063a43" stopOpacity="0.32" />
          <stop offset="100%" stopColor="#063a43" stopOpacity="0" />
        </radialGradient>
        <linearGradient id="os-sheen" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#ffffff" stopOpacity="0.30" />
          <stop offset="100%" stopColor="#ffffff" stopOpacity="0" />
        </linearGradient>
      </defs>

      {/* Ambient light */}
      <ellipse cx="216" cy="190" rx="184" ry="150" fill="url(#os-glow)" />
      <ellipse cx="110" cy="290" rx="110" ry="74" fill="url(#os-glow-warm)" />

      {/* Ground */}
      <ellipse cx="214" cy="338" rx="156" ry="24" fill="url(#os-shadow)" />

      {/* ---- Headquarters ---- */}
      <g className="os-building">
        <path d="M200 225 L270 190 L270 300 L200 335 Z" fill="url(#os-right)" />
        <path d="M130 190 L200 225 L200 335 L130 300 Z" fill="url(#os-left)" />
        <path d="M200 155 L270 190 L200 225 L130 190 Z" fill="url(#os-top)" />
        <path d="M130 190 L200 155 L270 190" stroke="#ffffff" strokeWidth="1.5" strokeLinecap="round" opacity="0.45" fill="none" />
        <path d="M200 225 L270 190 L270 222 L200 257 Z" fill="url(#os-sheen)" />

        {/* windows, left wall — a few lit, as if someone is at work */}
        <path d="M142 220 L160 229 L160 247 L142 238 Z" fill="url(#os-window)" />
        <path d="M170 234 L188 243 L188 261 L170 252 Z" fill="url(#os-window-dim)" />
        <path d="M142 252 L160 261 L160 279 L142 270 Z" fill="url(#os-window-dim)" />
        <path d="M170 266 L188 275 L188 293 L170 284 Z" fill="url(#os-window)" />

        {/* windows, right wall */}
        <path d="M212 243 L230 234 L230 252 L212 261 Z" fill="url(#os-window-dim)" />
        <path d="M240 229 L258 220 L258 238 L240 247 Z" fill="url(#os-window)" />
        <path d="M212 275 L230 266 L230 284 L212 293 Z" fill="url(#os-window)" />
        <path d="M240 261 L258 252 L258 270 L240 279 Z" fill="url(#os-window-dim)" />
      </g>

      {/* ---- Heart emblem hovering over the roof ---- */}
      <g className="os-heart-wrap">
        <ellipse cx="200" cy="178" rx="20" ry="7" fill="#063a43" opacity="0.14" />
        <path
          className="os-heart"
          d="M200 112 c-6 -8 -19 -6 -19 4.5 c0 8.5 10 15 19 22.5 c9 -7.5 19 -14 19 -22.5 c0 -10.5 -13 -12.5 -19 -4.5 Z"
          fill="url(#os-heart)"
        />
        <path d="M189 113 c2 -3 6 -4 9 -2" stroke="#ffffff" strokeWidth="2" strokeLinecap="round" opacity="0.6" fill="none" />
      </g>

      {/* ---- Published story floating beside the building ---- */}
      <g className="os-card">
        <path d="M290 155 L296 158 L296 242 L290 239 Z" fill="url(#os-page-edge)" />
        <path d="M296 158 L366 123 L366 207 L296 242 Z" fill="url(#os-page)" />
        <path d="M306 163 L356 138 L356 150 L306 175 Z" fill="url(#os-accent)" />
        <path d="M306 185 L356 160 L356 164 L306 189 Z" fill="#9fbcc1" opacity="0.8" />
        <path d="M306 197 L356 172 L356 176 L306 201 Z" fill="#9fbcc1" opacity="0.8" />
        <path d="M306 209 L340 192 L340 196 L306 213 Z" fill="#9fbcc1" opacity="0.8" />
        {/* published check */}
        <circle cx="366" cy="124" r="13" fill="#11b6c8" />
        <circle cx="366" cy="124" r="13" stroke="#ffffff" strokeWidth="2" opacity="0.7" />
        <path d="M360 124 L364.5 128.5 L372 120" stroke="#ffffff" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" fill="none" />
      </g>

      {/* ---- Donations gathered at the base ---- */}
      <g className="os-coins">
        <path d="M51 318 L51 324 A21 10.5 0 0 0 93 324 L93 318 Z" fill="url(#os-coin-side)" />
        <ellipse cx="72" cy="318" rx="21" ry="10.5" fill="url(#os-coin)" />
        <ellipse cx="72" cy="318" rx="12" ry="6" fill="#ffffff" opacity="0.3" />
        <path d="M56 307 L56 313 A21 10.5 0 0 0 98 313 L98 307 Z" fill="url(#os-coin-side)" />
        <ellipse cx="77" cy="307" rx="21" ry="10.5" fill="url(#os-coin)" />
        <ellipse cx="77" cy="307" rx="12" ry="6" fill="#ffffff" opacity="0.3" />
        <path d="M50 296 L50 302 A21 10.5 0 0 0 92 302 L92 296 Z" fill="url(#os-coin-side)" />
        <ellipse cx="71" cy="296" rx="21" ry="10.5" fill="url(#os-coin)" />
        <ellipse cx="71" cy="296" rx="12" ry="6" fill="#ffffff" opacity="0.3" />
        <ellipse cx="112" cy="332" rx="17" ry="8.5" fill="url(#os-coin)" opacity="0.92" />
        <ellipse cx="112" cy="332" rx="9" ry="4.5" fill="#ffffff" opacity="0.28" />
      </g>

      {/* ---- Motes rising ---- */}
      <g className="os-motes">
        <path
          className="os-mote os-mote-1"
          d="M258 104 c-2.4 -3.2 -7.4 -2.4 -7.4 1.8 c0 3.2 3.8 5.8 7.4 8.6 c3.6 -2.8 7.4 -5.4 7.4 -8.6 c0 -4.2 -5 -5 -7.4 -1.8 Z"
          fill="#11b6c8"
          opacity="0.75"
        />
        <circle className="os-mote os-mote-2" cx="150" cy="124" r="4.4" fill="#ffb347" opacity="0.8" />
        <circle className="os-mote os-mote-3" cx="330" cy="90" r="3.4" fill="#11b6c8" opacity="0.6" />
        <circle className="os-mote os-mote-4" cx="96" cy="250" r="3.6" fill="#ff9a3c" opacity="0.6" />
      </g>
    </svg>
  );
}
