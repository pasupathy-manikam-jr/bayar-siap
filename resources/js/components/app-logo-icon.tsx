import type { SVGAttributes } from 'react';

/** JobProof mark: a checkmark (proof approved). Inherits colour via fill/stroke="currentColor". */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
            <path
                d="M14 34l12 12 24-27"
                fill="none"
                stroke="currentColor"
                strokeWidth="8"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
