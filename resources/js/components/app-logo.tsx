import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name, description } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-muted text-foreground">
                <AppLogoIcon className="size-5 fill-current" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-base md:text-sm">
                <span className="mb-0.5 truncate font-semibold">{name}</span>
                {description && (
                    <span className="truncate text-base text-muted-foreground md:text-sm">
                        {description}
                    </span>
                )}
            </div>
        </>
    );
}
