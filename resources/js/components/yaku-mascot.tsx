interface YakuMascotProps {
    className?: string;
    /** Balancea las hojas con una animación lenta y discreta. */
    animado?: boolean;
}

/**
 * Yaku, el guardián de las áreas verdes: un brote con hoja, gota de agua y
 * gesto amable. Se dibuja en SVG para que se vea nítido en cualquier tamaño.
 * Los colores son fijos (no dependen del tema) porque siempre se apoya sobre
 * una superficie clara.
 */
export function YakuMascot({ className, animado = false }: YakuMascotProps) {
    return (
        <svg viewBox="0 0 64 64" className={className} aria-hidden="true" focusable="false">
            <g className={animado ? 'yaku-leaf' : undefined}>
                <path d="M32 27.5C28.3 17 19.2 10.6 11.3 12.9 5.4 14.6 5.9 22.6 13.8 25.7c5.7 2.2 12.2 2.3 18.2 1.8Z" fill="#047857" />
                <path d="M32 27.5C35.7 17 44.8 10.6 52.7 12.9c5.9 1.7 5.4 9.7-2.5 12.8-5.7 2.2-12.2 2.3-18.2 1.8Z" fill="#10b981" />
                <path d="M32 28.5v-5" fill="none" stroke="#065f46" strokeWidth="2.4" strokeLinecap="round" />
            </g>

            <ellipse cx="32" cy="42" rx="18.5" ry="17" fill="#d1fae5" stroke="#047857" strokeWidth="2.2" />
            <path d="M17.5 46.5c1.6 7.1 7.3 11.4 14.5 11.4s12.9-4.3 14.5-11.4" fill="#a7f3d0" opacity="0.55" />

            <circle cx="25.5" cy="40.5" r="2.5" fill="#064e3b" />
            <circle cx="38.5" cy="40.5" r="2.5" fill="#064e3b" />
            <circle cx="26.3" cy="39.7" r="0.8" fill="#f0fdf4" />
            <circle cx="39.3" cy="39.7" r="0.8" fill="#f0fdf4" />

            <ellipse cx="19.5" cy="47.5" rx="3.2" ry="2" fill="#fda4af" opacity="0.7" />
            <ellipse cx="44.5" cy="47.5" rx="3.2" ry="2" fill="#fda4af" opacity="0.7" />

            <path d="M28 46.5c1.3 2.1 2.6 3.1 4 3.1s2.7-1 4-3.1" fill="none" stroke="#064e3b" strokeWidth="2.1" strokeLinecap="round" />
        </svg>
    );
}
