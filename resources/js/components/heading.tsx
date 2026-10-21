export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    if (variant === 'small') {
        return (
            <header>
                <h2 className="mb-0.5 text-xl font-semibold md:text-lg">
                    {title}
                </h2>
                {description && (
                    <p className="text-base text-muted-foreground md:text-sm">
                        {description}
                    </p>
                )}
            </header>
        );
    }

    return (
        <header className="min-w-0 space-y-1">
            <h1 className="text-2xl leading-[1.4] font-semibold">{title}</h1>
            {description && (
                <p className="text-sm text-muted-foreground md:text-base">
                    {description}
                </p>
            )}
        </header>
    );
}
