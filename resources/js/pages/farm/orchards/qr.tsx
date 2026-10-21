import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/orchards';
import type { Orchard } from '@/types/farm';

type Label = { code: string; orchardName: string; svg: string };

export default function OrchardQr({
    orchard,
    labels,
}: {
    orchard: Orchard;
    labels: Label[];
}) {
    return (
        <div className="p-4 print:p-0">
            <Head title={`QR ${orchard.name}`} />
            <style>{`@media print { @page { size: A4; margin: 8mm; } }`}</style>
            <div className="mb-4 flex items-center justify-between gap-2 print:hidden">
                <Button variant="outline" asChild>
                    <Link href={show(orchard.id)}>กลับ</Link>
                </Button>
                <Button onClick={() => window.print()}>พิมพ์</Button>
            </div>
            {labels.length === 0 && (
                <p className="text-muted-foreground print:hidden">
                    ยังไม่มีต้นไม้ในสวนนี้
                </p>
            )}
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 print:grid-cols-4">
                {labels.map((label) => (
                    <div
                        key={label.code}
                        className="flex break-inside-avoid flex-col items-center gap-1 border p-2 text-center print:h-[45mm]"
                    >
                        <div
                            className="w-full max-w-[32mm] [&>svg]:h-auto [&>svg]:w-full"
                            dangerouslySetInnerHTML={{ __html: label.svg }}
                        />
                        <div className="text-xl font-semibold">
                            {label.code}
                        </div>
                        <div className="text-base text-muted-foreground md:text-sm">
                            {label.orchardName}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

OrchardQr.layout = (page: ReactNode) => page;
