/**
 * The Explore banner's centrepiece: a glowing orb of stories with article
 * cards orbiting it and a compass star above — the whole catalogue waiting
 * to be browsed.
 *
 * Same visual language as ImpactScene and GrowthScene (brand gradients, rim
 * light, soft shadows, rising motes) so the pages read as one system. Motion
 * lives in globals.css and is dropped for reduced-motion users.
 */
export default function DiscoverScene({ className }: { className?: string }) {
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
        {/* orb */}
        <radialGradient id="ds-orb" cx="34%" cy="28%" r="78%">
          <stop offset="0%" stopColor="#8df3fb" />
          <stop offset="42%" stopColor="#2ac6d8" />
          <stop offset="100%" stopColor="#06697a" />
        </radialGradient>
        <radialGradient id="ds-orb-rim" cx="50%" cy="50%" r="50%">
          <stop offset="72%" stopColor="#11b6c8" stopOpacity="0" />
          <stop offset="96%" stopColor="#8df3fb" stopOpacity="0.55" />
          <stop offset="100%" stopColor="#8df3fb" stopOpacity="0" />
        </radialGradient>
        <linearGradient id="ds-orb-spec" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#ffffff" stopOpacity="0.6" />
          <stop offset="100%" stopColor="#ffffff" stopOpacity="0" />
        </linearGradient>

        {/* cards */}
        <linearGradient id="ds-card" x1="0" y1="0" x2="0.4" y2="1">
          <stop offset="0%" stopColor="#ffffff" />
          <stop offset="100%" stopColor="#e2f1f3" />
        </linearGradient>
        <linearGradient id="ds-card-edge" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#c6dde1" />
          <stop offset="100%" stopColor="#a8c7cc" />
        </linearGradient>

        <linearGradient id="ds-star" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#ffd79a" />
          <stop offset="100%" stopColor="#ff7a00" />
        </linearGradient>

        <radialGradient id="ds-glow" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#11b6c8" stopOpacity="0.46" />
          <stop offset="55%" stopColor="#11b6c8" stopOpacity="0.12" />
          <stop offset="100%" stopColor="#11b6c8" stopOpacity="0" />
        </radialGradient>
        <radialGradient id="ds-glow-warm" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#ff7a00" stopOpacity="0.30" />
          <stop offset="100%" stopColor="#ff7a00" stopOpacity="0" />
        </radialGradient>
        <radialGradient id="ds-shadow" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#063a43" stopOpacity="0.30" />
          <stop offset="100%" stopColor="#063a43" stopOpacity="0" />
        </radialGradient>
      </defs>

      {/* Ambient light */}
      <ellipse cx="212" cy="186" rx="188" ry="152" fill="url(#ds-glow)" />
      <ellipse cx="292" cy="248" rx="118" ry="84" fill="url(#ds-glow-warm)" />

      {/* Ground shadow */}
      <ellipse cx="212" cy="318" rx="118" ry="22" fill="url(#ds-shadow)" />

      {/* ---- Orbit paths (behind the orb) ---- */}
      <g className="ds-orbits">
        <ellipse
          className="ds-orbit ds-orbit-1"
          cx="212" cy="196" rx="150" ry="56"
          stroke="#11b6c8" strokeWidth="1.4" strokeDasharray="4 10" opacity="0.4"
        />
        <ellipse
          className="ds-orbit ds-orbit-2"
          cx="212" cy="196" rx="112" ry="96"
          stroke="#ff9a3c" strokeWidth="1.2" strokeDasharray="3 11" opacity="0.28"
          transform="rotate(-18 212 196)"
        />
      </g>

      {/* ---- The orb of stories ---- */}
      <g className="ds-orb">
        <circle cx="212" cy="196" r="74" fill="url(#ds-orb)" />
        <circle cx="212" cy="196" r="74" fill="url(#ds-orb-rim)" />

        {/* meridians, so it reads as a sphere rather than a disc */}
        <g stroke="#ffffff" fill="none" opacity="0.3">
          <ellipse cx="212" cy="196" rx="74" ry="26" strokeWidth="1.3" />
          <ellipse cx="212" cy="196" rx="44" ry="74" strokeWidth="1.3" />
          <ellipse cx="212" cy="196" rx="12" ry="74" strokeWidth="1.1" opacity="0.7" />
        </g>
        <path d="M148 172 A 74 74 0 0 1 258 146" stroke="#ffffff" strokeWidth="1.2" opacity="0.28" fill="none" />

        {/* specular highlight */}
        <ellipse cx="186" cy="166" rx="30" ry="20" fill="url(#ds-orb-spec)" transform="rotate(-28 186 166)" />
      </g>

      {/* ---- Story cards orbiting ---- */}
      <g className="ds-card ds-card-1">
        <path d="M92 170 L146 152 L146 196 L92 214 Z" fill="url(#ds-card-edge)" />
        <path d="M88 166 L142 148 L142 192 L88 210 Z" fill="url(#ds-card)" />
        <path d="M94 170 L136 156 L136 164 L94 178 Z" fill="#11b6c8" opacity="0.55" />
        <g stroke="#9db9bd" strokeWidth="2" strokeLinecap="round" opacity="0.7">
          <path d="M94 186 L128 175" />
          <path d="M94 194 L120 185" />
        </g>
      </g>

      <g className="ds-card ds-card-2">
        <path d="M282 140 L336 158 L336 202 L282 184 Z" fill="url(#ds-card-edge)" />
        <path d="M286 136 L340 154 L340 198 L286 180 Z" fill="url(#ds-card)" />
        <path d="M292 144 L334 158 L334 166 L292 152 Z" fill="#ff7a00" opacity="0.5" />
        <g stroke="#9db9bd" strokeWidth="2" strokeLinecap="round" opacity="0.7">
          <path d="M292 162 L326 173" />
          <path d="M292 170 L318 179" />
        </g>
      </g>

      <g className="ds-card ds-card-3">
        <path d="M182 268 L242 268 L242 304 L182 304 Z" fill="url(#ds-card-edge)" />
        <path d="M180 264 L240 264 L240 300 L180 300 Z" fill="url(#ds-card)" />
        <path d="M186 271 L228 271 L228 279 L186 279 Z" fill="#11b6c8" opacity="0.45" />
        <g stroke="#9db9bd" strokeWidth="2" strokeLinecap="round" opacity="0.7">
          <path d="M186 287 L222 287" />
          <path d="M186 294 L212 294" />
        </g>
      </g>

      {/* ---- Compass star: the act of finding ---- */}
      <g className="ds-star">
        <path
          d="M212 34 L220 62 L248 70 L220 78 L212 106 L204 78 L176 70 L204 62 Z"
          fill="url(#ds-star)"
        />
        <path d="M212 50 L216 67 L233 70 L216 74 L212 91 L208 74 L191 70 L208 67 Z" fill="#ffffff" opacity="0.45" />
      </g>

      {/* ---- Sparks ---- */}
      <g className="ds-motes">
        <circle className="ds-mote ds-mote-1" cx="140" cy="112" r="4" fill="#ff9a3c" opacity="0.8" />
        <circle className="ds-mote ds-mote-2" cx="300" cy="96" r="3.4" fill="#11b6c8" opacity="0.7" />
        <circle className="ds-mote ds-mote-3" cx="352" cy="216" r="4.2" fill="#ffb347" opacity="0.7" />
        <circle className="ds-mote ds-mote-4" cx="72" cy="240" r="3.4" fill="#11b6c8" opacity="0.55" />
      </g>
    </svg>
  );
}
