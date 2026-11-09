import type { SVGAttributes } from 'react';

/** A report card: a page with three grade lines and a check. */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden
        >
            <path
                fillRule="evenodd"
                d="M6.5 2h11A2.5 2.5 0 0 1 20 4.5v15a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Zm0 2a.5.5 0 0 0-.5.5v15a.5.5 0 0 0 .5.5h11a.5.5 0 0 0 .5-.5v-15a.5.5 0 0 0-.5-.5h-11Z"
            />
            <rect x="8" y="7" width="8" height="2" rx="1" />
            <rect x="8" y="11" width="5" height="2" rx="1" />
            <path d="m8.3 16.4 1.4-1.4 1.3 1.3 3.3-3.3 1.4 1.4-4.7 4.7-2.7-2.7Z" />
        </svg>
    );
}
