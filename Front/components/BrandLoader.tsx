"use client";

interface BrandLoaderProps {
  label?: string;
  fullScreen?: boolean;
}

/**
 * Branded loading indicator: animates the NARLIT wordmark letter-by-letter
 * using the brand colors (NAR orange, LIT teal). Replaces generic dot spinners.
 */
export function BrandLoader({ label = "Loading", fullScreen = true }: BrandLoaderProps) {
  return (
    <div
      className={fullScreen ? "narlit-loader narlit-loader-full" : "narlit-loader"}
      suppressHydrationWarning
      role="status"
      aria-live="polite"
      aria-label={`${label}, please wait`}
    >
      <div className="narlit-loader-wordmark" aria-hidden="true">
        <span className="narlit-loader-letter narlit-loader-letter-orange">N</span>
        <span className="narlit-loader-letter narlit-loader-letter-orange">A</span>
        <span className="narlit-loader-letter narlit-loader-letter-orange">R</span>
        <span className="narlit-loader-letter narlit-loader-letter-teal">L</span>
        <span className="narlit-loader-letter narlit-loader-letter-teal">I</span>
        <span className="narlit-loader-letter narlit-loader-letter-teal">T</span>
      </div>
      <div className="narlit-loader-bar" aria-hidden="true">
        <span className="narlit-loader-bar-fill" />
      </div>
      <p className="narlit-loader-hint">{label}</p>
    </div>
  );
}
