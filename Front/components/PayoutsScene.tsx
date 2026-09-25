/**
 * The organization Payouts banner's centrepiece: an isometric safe with coins
 * dropping into its slot and a small stack already gathered beside it —
 * reader-funded earnings being collected and paid out.
 *
 * Same visual language as the other banner scenes (isometric slabs, rim light,
 * soft contact shadows, brand gradients). Motion lives in globals.css and is
 * dropped for reduced-motion users.
 */
export default function PayoutsScene({ className }: { className?: string }) {
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
        <linearGradient id="ps-top" x1="0.1" y1="0" x2="0.9" y2="1">
          <stop offset="0%" stopColor="#7deff8" />
          <stop offset="50%" stopColor="#2ac6d8" />
          <stop offset="100%" stopColor="#0f9cad" />
        </linearGradient>
        <linearGradient id="ps-left" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#0b8496" />
          <stop offset="100%" stopColor="#04505d" />
        </linearGradient>
        <linearGradient id="ps-right" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#14a7b8" />
          <stop offset="100%" stopColor="#076f80" />
        </linearGradient>
        <linearGradient id="ps-door" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#2ac6d8" />
          <stop offset="100%" stopColor="#0b8496" />
        </linearGradient>
        <linearGradient id="ps-dial" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#ffd79a" />
          <stop offset="100%" stopColor="#f07500" />
        </linearGradient>

        <linearGradient id="ps-coin" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#ffe3a8" />
          <stop offset="100%" stopColor="#ff9a1f" />
        </linearGradient>
        <linearGradient id="ps-coin-side" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#f0900f" />
          <stop offset="100%" stopColor="#c46a00" />
        </linearGradient>

        <radialGradient id="ps-glow" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#11b6c8" stopOpacity="0.40" />
          <stop offset="55%" stopColor="#11b6c8" stopOpacity="0.10" />
          <stop offset="100%" stopColor="#11b6c8" stopOpacity="0" />
        </radialGradient>
        <radialGradient id="ps-glow-warm" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#ff7a00" stopOpacity="0.30" />
          <stop offset="100%" stopColor="#ff7a00" stopOpacity="0" />
        </radialGradient>
        <radialGradient id="ps-shadow" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#063a43" stopOpacity="0.32" />
          <stop offset="100%" stopColor="#063a43" stopOpacity="0" />
        </radialGradient>
        <linearGradient id="ps-sheen" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#ffffff" stopOpacity="0.30" />
          <stop offset="100%" stopColor="#ffffff" stopOpacity="0" />
        </linearGradient>
      </defs>

      {/* Ambient light */}
      <ellipse cx="216" cy="190" rx="184" ry="150" fill="url(#ps-glow)" />
      <ellipse cx="200" cy="110" rx="90" ry="70" fill="url(#ps-glow-warm)" />

      {/* Ground */}
      <ellipse cx="214" cy="334" rx="156" ry="24" fill="url(#ps-shadow)" />

      {/* ---- Safe ---- */}
      <g className="ps-safe">
        <path d="M200 220 L270 185 L270 285 L200 320 Z" fill="url(#ps-right)" />
        <path d="M130 185 L200 220 L200 320 L130 285 Z" fill="url(#ps-left)" />
        <path d="M200 150 L270 185 L200 220 L130 185 Z" fill="url(#ps-top)" />
        <path d="M130 185 L200 150 L270 185" stroke="#ffffff" strokeWidth="1.5" strokeLinecap="round" opacity="0.45" fill="none" />
        <path d="M200 220 L270 185 L270 215 L200 250 Z" fill="url(#ps-sheen)" />

        {/* coin slot */}
        <path d="M186 192 L214 178" stroke="#03363f" strokeWidth="5" strokeLinecap="round" />

        {/* door with hinges and dial */}
        <path d="M208.4 227.8 L261.6 201.2 L261.6 277.2 L208.4 303.8 Z" fill="url(#ps-door)" />
        <path d="M208.4 227.8 L261.6 201.2" stroke="#ffffff" strokeWidth="1.3" opacity="0.5" />
        <path d="M204 238 L204 250" stroke="#03363f" strokeWidth="4" strokeLinecap="round" opacity="0.7" />
        <path d="M204 282 L204 294" stroke="#03363f" strokeWidth="4" strokeLinecap="round" opacity="0.7" />
        <ellipse cx="235" cy="252.5" rx="13" ry="16" transform="rotate(-26 235 252.5)" fill="url(#ps-dial)" />
        <ellipse cx="235" cy="252.5" rx="6" ry="7.5" transform="rotate(-26 235 252.5)" fill="#bf5700" />
        <path d="M235 252.5 L243 244" stroke="#ffffff" strokeWidth="2" strokeLinecap="round" opacity="0.8" />
      </g>

      {/* ---- Coins dropping into the slot ---- */}
      <g className="ps-drop ps-drop-1">
        <ellipse cx="200" cy="132" rx="7" ry="15" transform="rotate(-20 200 132)" fill="url(#ps-coin-side)" />
        <ellipse cx="203" cy="131" rx="6" ry="14" transform="rotate(-20 203 131)" fill="url(#ps-coin)" />
      </g>
      <g className="ps-drop ps-drop-2">
        <ellipse cx="214" cy="90" rx="7" ry="15" transform="rotate(-20 214 90)" fill="url(#ps-coin-side)" />
        <ellipse cx="217" cy="89" rx="6" ry="14" transform="rotate(-20 217 89)" fill="url(#ps-coin)" />
      </g>

      {/* ---- Gathered earnings beside the safe ---- */}
      <g className="ps-coins">
        <path d="M301 318 L301 324 A21 10.5 0 0 0 343 324 L343 318 Z" fill="url(#ps-coin-side)" />
        <ellipse cx="322" cy="318" rx="21" ry="10.5" fill="url(#ps-coin)" />
        <ellipse cx="322" cy="318" rx="12" ry="6" fill="#ffffff" opacity="0.3" />
        <path d="M306 307 L306 313 A21 10.5 0 0 0 348 313 L348 307 Z" fill="url(#ps-coin-side)" />
        <ellipse cx="327" cy="307" rx="21" ry="10.5" fill="url(#ps-coin)" />
        <ellipse cx="327" cy="307" rx="12" ry="6" fill="#ffffff" opacity="0.3" />
        <path d="M300 296 L300 302 A21 10.5 0 0 0 342 302 L342 296 Z" fill="url(#ps-coin-side)" />
        <ellipse cx="321" cy="296" rx="21" ry="10.5" fill="url(#ps-coin)" />
        <ellipse cx="321" cy="296" rx="12" ry="6" fill="#ffffff" opacity="0.3" />
      </g>

      {/* ---- Motes rising ---- */}
      <g className="ps-motes">
        <path
          className="ps-mote ps-mote-1"
          d="M112 150 c-2.4 -3.2 -7.4 -2.4 -7.4 1.8 c0 3.2 3.8 5.8 7.4 8.6 c3.6 -2.8 7.4 -5.4 7.4 -8.6 c0 -4.2 -5 -5 -7.4 -1.8 Z"
          fill="#ff7a00"
          opacity="0.85"
        />
        <circle className="ps-mote ps-mote-2" cx="300" cy="140" r="4.4" fill="#11b6c8" opacity="0.7" />
        <circle className="ps-mote ps-mote-3" cx="90" cy="250" r="3.6" fill="#ffb347" opacity="0.7" />
        <circle className="ps-mote ps-mote-4" cx="356" cy="220" r="3.4" fill="#11b6c8" opacity="0.6" />
      </g>
    </svg>
  );
}
