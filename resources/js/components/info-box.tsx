interface InfoBoxProps {
    title: string;
    description: string;
    children?: React.ReactNode;
}

export default function InfoBox({
    title,
    description,
    children,
}: InfoBoxProps) {
    return (
        <div className="rounded-md border border-b-2 border-neutral-200 bg-neutral-50 p-5 dark:border-neutral-800 dark:bg-neutral-950">
            <p className="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                {title}
            </p>
            <p className="mt-1 text-sm text-neutral-700 dark:text-neutral-300">
                {description}
            </p>
            {children && <div className="mt-3">{children}</div>}
        </div>
    );
}
