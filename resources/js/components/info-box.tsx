import { Info } from 'lucide-react';

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
        <div className="rounded-xl border bg-muted/30 p-5 dark:bg-muted/10">
            <div className="flex gap-3">
                <div className="shrink-0 rounded-lg bg-muted/50 p-2 dark:bg-muted/30">
                    <Info className="h-4 w-4 text-muted-foreground" />
                </div>
                <div className="flex-1">
                    <p className="text-sm font-medium text-foreground">
                        {title}
                    </p>
                    <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                        {description}
                    </p>
                    {children && <div className="mt-4">{children}</div>}
                </div>
            </div>
        </div>
    );
}
