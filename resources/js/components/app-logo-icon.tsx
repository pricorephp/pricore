import { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 267 317"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path d="M0 317V254H84L0 317Z" fill="url(#paint0_linear_8_9)" />
            <path
                d="M81.1378 0L0 73.5913H145.852C145.852 73.5913 183.032 71.7043 183.032 106.613C183.032 141.522 145.852 138.691 145.852 138.691H85.8551L0 217H175.484C175.484 217 267 217 267 108.5C267 0 175.484 0 175.484 0H81.1378Z"
                fill="url(#paint1_linear_8_9)"
            />
            <defs>
                <linearGradient
                    id="paint0_linear_8_9"
                    x1="133.5"
                    y1="0"
                    x2="133.5"
                    y2="115.73"
                    gradientUnits="userSpaceOnUse"
                >
                    <stop stopColor="#FF602E" />
                    <stop offset="1" stopColor="#FF3D00" />
                </linearGradient>
                <linearGradient
                    id="paint1_linear_8_9"
                    x1="133.5"
                    y1="0"
                    x2="133.5"
                    y2="115.73"
                    gradientUnits="userSpaceOnUse"
                >
                    <stop stopColor="#FF602E" />
                    <stop offset="1" stopColor="#FF3D00" />
                </linearGradient>
            </defs>
        </svg>
    );
}
