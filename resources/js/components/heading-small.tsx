export default function HeadingSmall({
    title,
    description,
}: {
    title: string;
    description?: string;
}) {
    return (
        <header>
            <h3 className="text-lg font-semibold">{title}</h3>
            {description && (
                <p className="mt-0.5 text-muted-foreground">{description}</p>
            )}
        </header>
    );
}
