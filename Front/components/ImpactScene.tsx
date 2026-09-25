/**
 * The welcome banner's 3D centrepiece: an isometric stack of books with an
 * open book floating above, whose pages shed glowing motes and coins that
 * rise into a heart — reading turning into funding, which is what NarLit does.
 *
 * Drawn as inline SVG rather than a raster render so it stays a few KB, is
 * crisp on any display, and uses the brand colours directly. The motion lives
 * in globals.css and is dropped for reduced-motion users.
 *
 * Geometry note: each book is a true isometric slab — a diamond top face
 * (T,R,B,L) plus the two faces hanging below its front vertex B. Rim light
 * traces L→T→R (the two edges facing the key light) and the paper block sits
 * just under the cover.
 */
export default function ImpactScene({ className }: { className?: string }) {
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
        {/* teal */}
        <linearGradient id="is-t-t" x1="0.1" y1="0" x2="0.9" y2="1">
          <stop offset="0%" stopColor="#7deff8" />
          <stop offset="45%" stopColor="#2ac6d8" />
          <stop offset="100%" stopColor="#0f9cad" />
        </linearGradient>
        <linearGradient id="is-t-l" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#0b8496" />
          <stop offset="100%" stopColor="#055e6e" />
        </linearGradient>
        <linearGradient id="is-t-r" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#14a7b8" />
          <stop offset="100%" stopColor="#0a7d8e" />
        </linearGradient>

        {/* orange */}
        <linearGradient id="is-o-t" x1="0.1" y1="0" x2="0.9" y2="1">
          <stop offset="0%" stopColor="#ffd79a" />
          <stop offset="45%" stopColor="#ff9c33" />
          <stop offset="100%" stopColor="#f07500" />
        </linearGradient>
        <linearGradient id="is-o-l" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#bf5700" />
          <stop offset="100%" stopColor="#8f4100" />
        </linearGradient>
        <linearGradient id="is-o-r" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#e06a00" />
          <stop offset="100%" stopColor="#ad4e00" />
        </linearGradient>

        {/* deep teal */}
        <linearGradient id="is-d-t" x1="0.1" y1="0" x2="0.9" y2="1">
          <stop offset="0%" stopColor="#4bd9e8" />
          <stop offset="50%" stopColor="#12a5b8" />
          <stop offset="100%" stopColor="#077e90" />
        </linearGradient>
        <linearGradient id="is-d-l" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#06646f" />
          <stop offset="100%" stopColor="#03454f" />
        </linearGradient>
        <linearGradient id="is-d-r" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#086f7e" />
          <stop offset="100%" stopColor="#04525f" />
        </linearGradient>

        {/* paper */}
        <linearGradient id="is-paper" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#ffffff" />
          <stop offset="100%" stopColor="#dbe9eb" />
        </linearGradient>
        <linearGradient id="is-paper-d" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#e9f3f4" />
          <stop offset="100%" stopColor="#c3d6d9" />
        </linearGradient>

        {/* open book pages */}
        <linearGradient id="is-pg-l" x1="1" y1="0" x2="0" y2="0">
          <stop offset="0%" stopColor="#ffffff" />
          <stop offset="100%" stopColor="#d5e9ec" />
        </linearGradient>
        <linearGradient id="is-pg-r" x1="0" y1="0" x2="1" y2="0">
          <stop offset="0%" stopColor="#ffffff" />
          <stop offset="100%" stopColor="#d5e9ec" />
        </linearGradient>

        {/* open book cover */}
        <linearGradient id="is-cov-l" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#0f9cad" />
          <stop offset="100%" stopColor="#077184" />
        </linearGradient>
        <linearGradient id="is-cov-r" x1="1" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#ff9c33" />
          <stop offset="100%" stopColor="#e06a00" />
        </linearGradient>

        {/* ambient occlusion between stacked books */}
        <radialGradient id="is-ao" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#04424c" stopOpacity="0.30" />
          <stop offset="60%" stopColor="#04424c" stopOpacity="0.13" />
          <stop offset="100%" stopColor="#04424c" stopOpacity="0" />
        </radialGradient>

        {/* specular sweep */}
        <linearGradient id="is-sheen" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#ffffff" stopOpacity="0.34" />
          <stop offset="100%" stopColor="#ffffff" stopOpacity="0" />
        </linearGradient>

        {/* coin */}
        <linearGradient id="is-coin" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#ffd98a" />
          <stop offset="100%" stopColor="#ff9a1f" />
        </linearGradient>

        <radialGradient id="is-glow" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#11b6c8" stopOpacity="0.42" />
          <stop offset="55%" stopColor="#11b6c8" stopOpacity="0.10" />
          <stop offset="100%" stopColor="#11b6c8" stopOpacity="0" />
        </radialGradient>
        <radialGradient id="is-glow-warm" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#ff7a00" stopOpacity="0.32" />
          <stop offset="100%" stopColor="#ff7a00" stopOpacity="0" />
        </radialGradient>
        <radialGradient id="is-halo" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#ff7a00" stopOpacity="0.55" />
          <stop offset="60%" stopColor="#ff7a00" stopOpacity="0.12" />
          <stop offset="100%" stopColor="#ff7a00" stopOpacity="0" />
        </radialGradient>
        <radialGradient id="is-shadow" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#063a43" stopOpacity="0.34" />
          <stop offset="100%" stopColor="#063a43" stopOpacity="0" />
        </radialGradient>
      </defs>

      {/* Ambient light */}
      <ellipse cx="212" cy="176" rx="186" ry="152" fill="url(#is-glow)" />
      <ellipse cx="268" cy="258" rx="126" ry="88" fill="url(#is-glow-warm)" />

      {/* Orbit ring, well below the books so it never cuts across them */}
      <ellipse
        className="is-ring"
        cx="210"
        cy="316"
        rx="158"
        ry="30"
        stroke="#11b6c8"
        strokeWidth="1.3"
        strokeDasharray="3 12"
        opacity="0.35"
      />

      {/* Ground shadow */}
      <ellipse cx="210" cy="332" rx="144" ry="25" fill="url(#is-shadow)" />

      {/* book-3 */}
      <g className="is-book is-book-3">
        <path d="M326 262 L210 303 L210 321 L326 280 Z" fill="url(#is-d-r)" />
        <path d="M94 262 L210 303 L210 321 L94 280 Z" fill="url(#is-d-l)" />
        <path d="M326 280 L210 321 L210 327 L326 286 Z" fill="url(#is-paper)" opacity="0.96" />
        <path d="M94 280 L210 321 L210 327 L94 286 Z" fill="url(#is-paper-d)" opacity="0.9" />
        <path d="M210 221 L326 262 L210 303 L94 262 Z" fill="url(#is-d-t)" />
        <path d="M94 262 L210 221 L326 262" stroke="#ffffff" strokeWidth="1.6" strokeLinecap="round" opacity="0.5" fill="none" />
        <path d="M210 232 L294 262 L210 292 L126 262 Z" stroke="#ffffff" strokeWidth="1.1" opacity="0.22" fill="none" />
        <path d="M259 304 L268 308 L268 330 L263 325 L258 332 L258 309 Z" fill="#ff9a3c" opacity="0.95" />
      </g>

      {/* contact shadow cast by book-2 onto book-3 */}
      <ellipse cx="205" cy="270" rx="100" ry="32" fill="url(#is-ao)" />

      {/* book-2 */}
      <g className="is-book is-book-2">
        <path d="M307 236 L203 273 L203 290 L307 253 Z" fill="url(#is-o-r)" />
        <path d="M99 236 L203 273 L203 290 L99 253 Z" fill="url(#is-o-l)" />
        <path d="M307 253 L203 290 L203 296 L307 259 Z" fill="url(#is-paper)" opacity="0.96" />
        <path d="M99 253 L203 290 L203 296 L99 259 Z" fill="url(#is-paper-d)" opacity="0.9" />
        <path d="M203 199 L307 236 L203 273 L99 236 Z" fill="url(#is-o-t)" />
        <path d="M99 236 L203 199 L307 236" stroke="#ffffff" strokeWidth="1.6" strokeLinecap="round" opacity="0.5" fill="none" />
        <path d="M203 209 L278 236 L203 263 L128 236 Z" stroke="#ffffff" strokeWidth="1.1" opacity="0.22" fill="none" />
      </g>

      {/* contact shadow cast by book-1 onto book-2 */}
      <ellipse cx="212" cy="245" rx="88" ry="28" fill="url(#is-ao)" />

      {/* book-1 */}
      <g className="is-book is-book-1">
        <path d="M306 211 L214 244 L214 260 L306 227 Z" fill="url(#is-t-r)" />
        <path d="M122 211 L214 244 L214 260 L122 227 Z" fill="url(#is-t-l)" />
        <path d="M306 227 L214 260 L214 266 L306 233 Z" fill="url(#is-paper)" opacity="0.96" />
        <path d="M122 227 L214 260 L214 266 L122 233 Z" fill="url(#is-paper-d)" opacity="0.9" />
        <path d="M214 178 L306 211 L214 244 L122 211 Z" fill="url(#is-t-t)" />
        <path d="M122 211 L214 178 L306 211" stroke="#ffffff" strokeWidth="1.6" strokeLinecap="round" opacity="0.5" fill="none" />
        <path d="M214 187 L280 211 L214 235 L148 211 Z" stroke="#ffffff" strokeWidth="1.1" opacity="0.22" fill="none" />
        {/* specular sweep — the key light grazing the cover */}
        <path d="M214 178 L306 211 L268 225 L176 192 Z" fill="url(#is-sheen)" />
      </g>

      {/* ---- Open book, floating above the stack ---- */}
      <g transform="translate(0, 22)">
        <g className="is-open">
          <ellipse cx="212" cy="112" rx="104" ry="48" fill="url(#is-glow)" />

          {/* coloured cover, just proud of the pages on both sides */}
          <path
            d="M212 146 C 192 130 158 114 118 110 L118 122 C 158 126 192 142 212 158 Z"
            fill="url(#is-cov-l)"
          />
          <path
            d="M212 146 C 232 130 266 114 306 110 L306 122 C 266 126 232 142 212 158 Z"
            fill="url(#is-cov-r)"
          />

          {/* fanned page edges behind the top sheet */}
          <path d="M212 138 C 194 124 162 110 126 106 L126 112 C 162 116 194 130 212 144 Z" fill="#b9d6da" opacity="0.8" />
          <path d="M212 138 C 230 124 262 110 298 106 L298 112 C 262 116 230 130 212 144 Z" fill="#c8e0e3" opacity="0.8" />

          {/* the two open pages */}
          <path
            d="M122 88 C 158 92 192 108 212 124 L212 148 C 192 132 158 116 122 112 Z"
            fill="url(#is-pg-l)"
          />
          <path
            d="M302 88 C 266 92 232 108 212 124 L212 148 C 232 132 266 116 302 112 Z"
            fill="url(#is-pg-r)"
          />

          {/* page curl highlight along the outer edges */}
          <path d="M122 88 C 158 92 192 108 212 124" stroke="#ffffff" strokeWidth="2" opacity="0.9" fill="none" />
          <path d="M302 88 C 266 92 232 108 212 124" stroke="#ffffff" strokeWidth="2" opacity="0.9" fill="none" />

          {/* spine */}
          <path d="M212 124 L212 152" stroke="#8fbac1" strokeWidth="2" strokeLinecap="round" opacity="0.85" />

          {/* text lines following the page curve */}
          <g stroke="#11b6c8" strokeWidth="2.6" strokeLinecap="round" opacity="0.55" fill="none">
            <path d="M142 99 C 168 104 192 116 204 126" />
            <path d="M140 108 C 164 113 186 124 198 133" />
          </g>
          <g stroke="#ff7a00" strokeWidth="2.6" strokeLinecap="round" opacity="0.55" fill="none">
            <path d="M282 99 C 256 104 232 116 220 126" />
            <path d="M284 108 C 260 113 238 124 226 133" />
          </g>
        </g>
      </g>

      {/* ---- What the reading becomes: motes, coins, a heart ---- */}
      <g transform="translate(0, 30)">
        <g className="is-motes">
          <circle className="is-mote is-mote-1" cx="166" cy="78" r="4.6" fill="#ff7a00" opacity="0.85" />
          <circle className="is-mote is-mote-3" cx="258" cy="76" r="4" fill="#ffb347" opacity="0.8" />
          <circle className="is-mote is-mote-4" cx="292" cy="58" r="3.2" fill="#11b6c8" opacity="0.6" />
          <circle className="is-mote is-mote-5" cx="132" cy="58" r="3.4" fill="#11b6c8" opacity="0.55" />

          {/* coins — the funding half of the idea */}
          <g className="is-mote is-mote-2">
            <ellipse cx="190" cy="62" rx="9" ry="5.4" fill="url(#is-coin)" />
            <ellipse cx="190" cy="60.4" rx="9" ry="5.4" fill="#ffe7b0" opacity="0.75" />
            <ellipse cx="190" cy="60.4" rx="4.4" ry="2.6" fill="#ffb347" opacity="0.7" />
          </g>
          <g className="is-mote is-mote-6">
            <ellipse cx="236" cy="54" rx="7.6" ry="4.6" fill="url(#is-coin)" />
            <ellipse cx="236" cy="52.6" rx="7.6" ry="4.6" fill="#ffe7b0" opacity="0.75" />
            <ellipse cx="236" cy="52.6" rx="3.6" ry="2.1" fill="#ffb347" opacity="0.7" />
          </g>
        </g>

        {/* heart, haloed */}
        <g className="is-heart-wrap">
          <circle cx="212" cy="26" r="30" fill="url(#is-halo)" />
          <path
            className="is-heart"
            d="M212 14 c-4.6 -6.2 -14.2 -4.6 -14.2 3.4 c0 6.2 7.2 11 14.2 16.6 c7 -5.6 14.2 -10.4 14.2 -16.6 c0 -8 -9.6 -9.6 -14.2 -3.4 Z"
            fill="#ff7a00"
          />
          <path
            d="M205 19 c0 -3 3 -4 4.6 -2.2"
            stroke="#ffffff"
            strokeWidth="2"
            strokeLinecap="round"
            opacity="0.6"
            fill="none"
          />
        </g>
      </g>
    </svg>
  );
}
