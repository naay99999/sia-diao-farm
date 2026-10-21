import type { SVGAttributes } from 'react';

/**
 * The farm's mark: a durian, spiked husk and stem, drawn as one flat shape.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M11.2 0h1.6v3.4h-1.6z" />
            <path d="M12 2.61l1.42 1.73 1.88-1.09.8 2.16 2.1-.32.08 2.32 2.07.5-.64 2.21 1.79 1.24-1.3 1.84 1.3 1.84-1.79 1.24.64 2.21-2.07.5-.08 2.32-2.1-.32-.8 2.16-1.88-1.09L12 23.79l-1.42-1.73-1.88 1.09-.8-2.16-2.1.32-.08-2.32-2.07-.5.64-2.21-1.79-1.24 1.3-1.84-1.3-1.84 1.79-1.24-.64-2.21 2.07-.5.08-2.32 2.1.32.8-2.16 1.88 1.09z" />
        </svg>
    );
}
