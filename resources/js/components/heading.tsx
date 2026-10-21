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
                <h2 className="mb-0.5 text-base font-medium">{title}</h2>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </header>
        );
    }

    return (
        <header className="min-w-0 space-y-1">
            <h1 className="text-2xl leading-tight font-semibold tracking-tight md:text-[28px]">
                {title}
            </h1>
            {description && (
                <p className="text-sm text-muted-foreground md:text-base">
                    {description}
                </p>
            )}
        </header>
    );
}
