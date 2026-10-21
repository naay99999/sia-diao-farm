import { Head, setLayoutProps } from '@inertiajs/react';
import Heading from '@/components/heading';
import { index, show } from '@/routes/orchards';
import { show as showSeason } from '@/routes/seasons';
import type { Orchard, Season } from '@/types/farm';

export default function SeasonShow({
    season,
    orchard,
}: {
    season: Season;
    orchard: Orchard;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'สวน', href: index() },
            { title: orchard.name, href: show(orchard.id) },
            { title: season.name, href: showSeason(season.id) },
        ],
    });

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title={season.name} />
            <Heading title={season.name} description={orchard.name} />
        </div>
    );
}
