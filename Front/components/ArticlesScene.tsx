/**
 * The organization Articles banner's centrepiece: an isometric stack of
 * manuscripts with the top sheet written on and a pen hovering over it —
 * the organization's stories in the making.
 *
 * Same visual language as the other banner scenes (isometric slabs, rim light,
 * soft contact shadows, brand gradients). Motion lives in globals.css and is
 * dropped for reduced-motion users.
 */
export default function ArticlesScene({ className }: { className?: string }) {
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
        <linearGradient id="as-t-t" x1="0.1" y1="0" x2="0.9" y2="1">
          <stop offset="0%" stopColor="#7deff8" />
          <stop offset="50%" stopColor="#2ac6d8" />
          <stop offset="100%" stopColor="#0f9cad" />
        </linearGradient>
        <linearGradient id="as-t-l" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#0b8496" />
          <stop offset="100%" stopColor="#04505d" />
        </linearGradient>
        <linearGradient id="as-t-r" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#14a7b8" />
          <stop offset="100%" stopColor="#076f80" />
        </linearGradient>
        <linearGradient id="as-o-t" x1="0.1" y1="0" x2="0.9" y2="1">
          <stop offset="0%" stopColor="#ffd79a" />
          <stop offset="50%" stopColor="#ff9c33" />
          <stop offset="100%" stopColor="#f07500" />
        </linearGradient>
        <linearGradient id="as-o-l" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#bf5700" />
          <stop offset="100%" stopColor="#7d3800" />
        </linearGradient>
        <linearGradient id="as-o-r" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#e06a00" />
          <stop offset="100%" stopColor="#9c4600" />
        </linearGradient>
        <linearGradient id="as-p-t" x1="0.1" y1="0" x2="0.9" y2="1">
          <stop offset="0%" stopColor="#ffffff" />
          <stop offset="100%" stopColor="#e3eef0" />
        </linearGradient>
        <linearGradient id="as-p-s" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#c3d6d9" />
          <stop offset="100%" stopColor="#8fb0b5" />
        </linearGradient>

        <radialGradient id="as-glow" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#11b6c8" stopOpacity="0.40" />
          <stop offset="55%" stopColor="#11b6c8" stopOpacity="0.10" />
          <stop offset="100%" stopColor="#11b6c8" stopOpacity="0" />
        </radialGradient>
        <radialGradient id="as-glow-warm" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#ff7a00" stopOpacity="0.28" />
          <stop offset="100%" stopColor="#ff7a00" stopOpacity="0" />
        </radialGradient>
        <radialGradient id="as-shadow" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#063a43" stopOpacity="0.32" />
          <stop offset="100%" stopColor="#063a43" stopOpacity="0" />
        </radialGradient>
      </defs>

      {/* Ambient light */}
      <ellipse cx="210" cy="200" rx="184" ry="150" fill="url(#as-glow)" />
      <ellipse cx="300" cy="150" rx="100" ry="70" fill="url(#as-glow-warm)" />

      {/* Ground */}
      <ellipse cx="204" cy="330" rx="150" ry="24" fill="url(#as-shadow)" />

      {/* ---- Manuscript stack, bottom to top ---- */}
      <g className="as-sheet as-sheet-1">
        <path d="M110 290 L200 335 L200 343 L110 298 Z" fill="url(#as-t-l)" />
        <path d="M200 335 L290 290 L290 298 L200 343 Z" fill="url(#as-t-r)" />
        <path d="M200 245 L290 290 L200 335 L110 290 Z" fill="url(#as-t-t)" />
      </g>
      <g className="as-sheet as-sheet-2">
        <path d="M110 262 L200 307 L200 315 L110 270 Z" fill="url(#as-o-l)" />
        <path d="M200 307 L290 262 L290 270 L200 315 Z" fill="url(#as-o-r)" />
        <path d="M200 217 L290 262 L200 307 L110 262 Z" fill="url(#as-o-t)" />
        <path d="M110 262 L200 217 L290 262" stroke="#ffffff" strokeWidth="1.5" strokeLinecap="round" opacity="0.45" fill="none" />
      </g>
      <g className="as-sheet as-sheet-3">
        <path d="M110 234 L200 279 L200 287 L110 242 Z" fill="url(#as-p-s)" />
        <path d="M200 279 L290 234 L290 242 L200 287 Z" fill="url(#as-p-s)" />
        <path d="M200 189 L290 234 L200 279 L110 234 Z" fill="url(#as-p-t)" />
        <path d="M110 234 L200 189 L290 234" stroke="#ffffff" strokeWidth="1.5" strokeLinecap="round" opacity="0.8" fill="none" />
        {/* writing on the top sheet */}
        <path d="M140 237 L200 207" stroke="#ff8a1f" strokeWidth="7" strokeLinecap="round" />
        <path d="M164 249 L224 219" stroke="#9fbcc1" strokeWidth="4" strokeLinecap="round" />
        <path d="M176 255 L236 225" stroke="#9fbcc1" strokeWidth="4" strokeLinecap="round" />
        <path d="M188 261 L226 242" stroke="#9fbcc1" strokeWidth="4" strokeLinecap="round" />
      </g>

      {/* ---- Pen hovering over the page ---- */}
      <g className="as-pen">
        <ellipse cx="258" cy="222" rx="28" ry="7" fill="#063a43" opacity="0.12" />
        <path d="M251.9 168.7 L331.9 128.7 L335 135 L255 175 Z" fill="#2ac6d8" />
        <path d="M255 175 L335 135 L338.1 141.3 L258.1 181.3 Z" fill="#0b8496" />
        <path d="M331.9 128.7 L340.8 124.2 L347 136.8 L338.1 141.3 Z" fill="url(#as-o-t)" />
        <path d="M251.9 168.7 L258.1 181.3 L238.9 183 Z" fill="#ffe3b8" />
        <path d="M243.3 178.2 L245.3 182.4 L238.9 183 Z" fill="#04505d" />
        <path d="M254 171 L333 131.5" stroke="#ffffff" strokeWidth="1.2" strokeLinecap="round" opacity="0.55" />
      </g>

      {/* ---- Motes rising from the page ---- */}
      <g className="as-motes">
        <path
          className="as-mote as-mote-1"
          d="M150 150 c-3 -4 -9.2 -3 -9.2 2.2 c0 4 4.7 7.2 9.2 10.8 c4.5 -3.6 9.2 -6.8 9.2 -10.8 c0 -5.2 -6.2 -6.2 -9.2 -2.2 Z"
          fill="#ff7a00"
          opacity="0.9"
        />
        <circle className="as-mote as-mote-2" cx="210" cy="140" r="4.4" fill="#11b6c8" opacity="0.7" />
        <circle className="as-mote as-mote-3" cx="104" cy="196" r="3.6" fill="#ffb347" opacity="0.7" />
        <circle className="as-mote as-mote-4" cx="352" cy="190" r="3.4" fill="#11b6c8" opacity="0.6" />
      </g>
    </svg>
  );
}
