/**
 * The My Impact banner's centrepiece: three rising isometric columns with a
 * sprout breaking out of the tallest and coins gathered at its base — the
 * reader's giving compounding into something that grows.
 *
 * Same visual language as ImpactScene (isometric slabs, rim light on the two
 * lit edges, soft contact shadows, brand gradients) so the pages read as one
 * system. Motion lives in globals.css and is dropped for reduced-motion users.
 */
export default function GrowthScene({ className }: { className?: string }) {
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
        <linearGradient id="gs-t-t" x1="0.1" y1="0" x2="0.9" y2="1">
          <stop offset="0%" stopColor="#7deff8" />
          <stop offset="50%" stopColor="#2ac6d8" />
          <stop offset="100%" stopColor="#0f9cad" />
        </linearGradient>
        <linearGradient id="gs-t-l" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#0b8496" />
          <stop offset="100%" stopColor="#04505d" />
        </linearGradient>
        <linearGradient id="gs-t-r" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#14a7b8" />
          <stop offset="100%" stopColor="#076f80" />
        </linearGradient>

        <linearGradient id="gs-o-t" x1="0.1" y1="0" x2="0.9" y2="1">
          <stop offset="0%" stopColor="#ffd79a" />
          <stop offset="50%" stopColor="#ff9c33" />
          <stop offset="100%" stopColor="#f07500" />
        </linearGradient>
        <linearGradient id="gs-o-l" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#bf5700" />
          <stop offset="100%" stopColor="#7d3800" />
        </linearGradient>
        <linearGradient id="gs-o-r" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#e06a00" />
          <stop offset="100%" stopColor="#9c4600" />
        </linearGradient>

        <linearGradient id="gs-d-t" x1="0.1" y1="0" x2="0.9" y2="1">
          <stop offset="0%" stopColor="#4bd9e8" />
          <stop offset="50%" stopColor="#12a5b8" />
          <stop offset="100%" stopColor="#077e90" />
        </linearGradient>
        <linearGradient id="gs-d-l" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#06646f" />
          <stop offset="100%" stopColor="#02373f" />
        </linearGradient>
        <linearGradient id="gs-d-r" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#086f7e" />
          <stop offset="100%" stopColor="#034450" />
        </linearGradient>

        <linearGradient id="gs-leaf" x1="0" y1="1" x2="1" y2="0">
          <stop offset="0%" stopColor="#0f9cad" />
          <stop offset="100%" stopColor="#7deff8" />
        </linearGradient>
        <linearGradient id="gs-leaf-2" x1="1" y1="1" x2="0" y2="0">
          <stop offset="0%" stopColor="#0b8496" />
          <stop offset="100%" stopColor="#4bd9e8" />
        </linearGradient>
        <linearGradient id="gs-coin" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#ffe3a8" />
          <stop offset="100%" stopColor="#ff9a1f" />
        </linearGradient>
        <linearGradient id="gs-coin-side" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#f0900f" />
          <stop offset="100%" stopColor="#c46a00" />
        </linearGradient>

        <radialGradient id="gs-glow" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#11b6c8" stopOpacity="0.40" />
          <stop offset="55%" stopColor="#11b6c8" stopOpacity="0.10" />
          <stop offset="100%" stopColor="#11b6c8" stopOpacity="0" />
        </radialGradient>
        <radialGradient id="gs-glow-warm" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#ff7a00" stopOpacity="0.30" />
          <stop offset="100%" stopColor="#ff7a00" stopOpacity="0" />
        </radialGradient>
        <radialGradient id="gs-shadow" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#063a43" stopOpacity="0.32" />
          <stop offset="100%" stopColor="#063a43" stopOpacity="0" />
        </radialGradient>
        <linearGradient id="gs-sheen" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#ffffff" stopOpacity="0.30" />
          <stop offset="100%" stopColor="#ffffff" stopOpacity="0" />
        </linearGradient>
      </defs>

      {/* Ambient light */}
      <ellipse cx="216" cy="180" rx="184" ry="150" fill="url(#gs-glow)" />
      <ellipse cx="160" cy="280" rx="118" ry="80" fill="url(#gs-glow-warm)" />

      {/* Ground */}
      <ellipse cx="212" cy="336" rx="152" ry="24" fill="url(#gs-shadow)" />

      {/* ---- Rising columns ---- */}
      <g className="gs-bar gs-bar-1">
        <path d="M180 268 L140 288 L140 334 L180 314 Z" fill="url(#gs-d-r)" />
        <path d="M100 268 L140 288 L140 334 L100 314 Z" fill="url(#gs-d-l)" />
        <path d="M140 248 L180 268 L140 288 L100 268 Z" fill="url(#gs-d-t)" />
        <path d="M100 268 L140 248 L180 268" stroke="#ffffff" strokeWidth="1.5" strokeLinecap="round" opacity="0.45" fill="none" />
      </g>

      <g className="gs-bar gs-bar-2">
        <path d="M250 240 L210 260 L210 334 L250 314 Z" fill="url(#gs-o-r)" />
        <path d="M170 240 L210 260 L210 334 L170 314 Z" fill="url(#gs-o-l)" />
        <path d="M210 220 L250 240 L210 260 L170 240 Z" fill="url(#gs-o-t)" />
        <path d="M170 240 L210 220 L250 240" stroke="#ffffff" strokeWidth="1.5" strokeLinecap="round" opacity="0.45" fill="none" />
      </g>

      <g className="gs-bar gs-bar-3">
        <path d="M320 212 L280 232 L280 334 L320 314 Z" fill="url(#gs-t-r)" />
        <path d="M240 212 L280 232 L280 334 L240 314 Z" fill="url(#gs-t-l)" />
        <path d="M280 192 L320 212 L280 232 L240 212 Z" fill="url(#gs-t-t)" />
        <path d="M240 212 L280 192 L320 212" stroke="#ffffff" strokeWidth="1.5" strokeLinecap="round" opacity="0.45" fill="none" />
        <path d="M280 192 L320 212 L320 244 L280 224 Z" fill="url(#gs-sheen)" />
      </g>

      {/* ---- Sprout breaking out of the tallest column ---- */}
      <g className="gs-sprout">
        <path
          d="M280 196 C 280 172 278 152 276 136"
          stroke="#0f9cad"
          strokeWidth="4"
          strokeLinecap="round"
          fill="none"
        />
        {/* right leaf */}
        <path
          d="M277 158 C 296 152 314 158 320 172 C 302 180 284 174 277 158 Z"
          fill="url(#gs-leaf)"
        />
        <path d="M280 163 C 294 164 306 169 315 173" stroke="#ffffff" strokeWidth="1.3" opacity="0.5" fill="none" />
        {/* left leaf */}
        <path
          d="M276 142 C 258 134 240 139 233 152 C 250 161 269 157 276 142 Z"
          fill="url(#gs-leaf-2)"
        />
        <path d="M273 147 C 260 147 248 151 239 155" stroke="#ffffff" strokeWidth="1.3" opacity="0.5" fill="none" />
        {/* bud */}
        <circle cx="276" cy="130" r="7" fill="url(#gs-leaf)" />
        <circle cx="274" cy="128" r="2.6" fill="#ffffff" opacity="0.55" />
      </g>

      {/* ---- Coins at the base, clear of the columns ---- */}
      <g className="gs-coins">
        {/* bottom coin */}
        <path d="M51 318 L51 324 A21 10.5 0 0 0 93 324 L93 318 Z" fill="url(#gs-coin-side)" />
        <ellipse cx="72" cy="318" rx="21" ry="10.5" fill="url(#gs-coin)" />
        <ellipse cx="72" cy="318" rx="12" ry="6" fill="#ffffff" opacity="0.3" />
        {/* middle coin */}
        <path d="M56 307 L56 313 A21 10.5 0 0 0 98 313 L98 307 Z" fill="url(#gs-coin-side)" />
        <ellipse cx="77" cy="307" rx="21" ry="10.5" fill="url(#gs-coin)" />
        <ellipse cx="77" cy="307" rx="12" ry="6" fill="#ffffff" opacity="0.3" />
        {/* top coin */}
        <path d="M50 296 L50 302 A21 10.5 0 0 0 92 302 L92 296 Z" fill="url(#gs-coin-side)" />
        <ellipse cx="71" cy="296" rx="21" ry="10.5" fill="url(#gs-coin)" />
        <ellipse cx="71" cy="296" rx="12" ry="6" fill="#ffffff" opacity="0.3" />
        {/* a coin lying flat beside the stack */}
        <ellipse cx="116" cy="330" rx="17" ry="8.5" fill="url(#gs-coin)" opacity="0.92" />
        <ellipse cx="116" cy="330" rx="9" ry="4.5" fill="#ffffff" opacity="0.28" />
      </g>

      {/* ---- Hearts rising from the growth ---- */}
      <g className="gs-motes">
        <path
          className="gs-mote gs-mote-1"
          d="M196 96 c-3 -4 -9.2 -3 -9.2 2.2 c0 4 4.7 7.2 9.2 10.8 c4.5 -3.6 9.2 -6.8 9.2 -10.8 c0 -5.2 -6.2 -6.2 -9.2 -2.2 Z"
          fill="#ff7a00"
          opacity="0.9"
        />
        <path
          className="gs-mote gs-mote-2"
          d="M320 108 c-2.4 -3.2 -7.4 -2.4 -7.4 1.8 c0 3.2 3.8 5.8 7.4 8.6 c3.6 -2.8 7.4 -5.4 7.4 -8.6 c0 -4.2 -5 -5 -7.4 -1.8 Z"
          fill="#11b6c8"
          opacity="0.75"
        />
        <circle className="gs-mote gs-mote-3" cx="240" cy="92" r="4.4" fill="#ffb347" opacity="0.8" />
        <circle className="gs-mote gs-mote-4" cx="160" cy="132" r="3.4" fill="#11b6c8" opacity="0.6" />
        <circle className="gs-mote gs-mote-5" cx="346" cy="150" r="3.6" fill="#ff9a3c" opacity="0.6" />
      </g>
    </svg>
  );
}
