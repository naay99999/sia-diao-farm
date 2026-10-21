import { Form, Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import OrchardController from '@/actions/App/Http/Controllers/Farm/OrchardController';
import { EmptyState } from '@/components/farm/empty-state';
import { SubmitButton } from '@/components/farm/submit-button';
import Heading from '@/components/heading';
import OrchardFormFields from '@/components/orchard-form-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatNumber } from '@/lib/format';
import { index, show } from '@/routes/orchards';
import type { OrchardSummary } from '@/types/farm';

export default function OrchardsIndex({
    orchards,
}: {
    orchards: OrchardSummary[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <div className="flex flex-1 flex-col gap-6 px-4 py-5 md:px-6 md:py-6">
            <Head title="สวน" />
            <div className="flex items-start justify-between gap-3">
                <Heading title="สวน" description={`${orchards.length} สวน`} />
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogTrigger asChild>
                        <Button variant="outline">เพิ่มสวน</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>เพิ่มสวน</DialogTitle>
                            <DialogDescription>
                                กรอกข้อมูลสวนใหม่
                            </DialogDescription>
                        </DialogHeader>
                        <Form
                            {...OrchardController.store.form()}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <OrchardFormFields errors={errors} />
                                    <SubmitButton processing={processing} />
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>

            {orchards.length === 0 ? (
                <EmptyState
                    action={
                        <Button onClick={() => setOpen(true)}>
                            เพิ่มสวนแรก
                        </Button>
                    }
                >
                    ยังไม่มีสวน เพิ่มสวนแรกเพื่อเริ่มบันทึกต้นไม้และผลผลิต
                </EmptyState>
            ) : (
                <ul className="grid gap-3 lg:grid-cols-2">
                    {orchards.map((orchard) => (
                        <li
                            key={orchard.id}
                            className="rounded-lg border bg-card"
                        >
                            <Link
                                href={show(orchard.id)}
                                className="flex items-center gap-3 rounded-lg px-4 py-4 transition-colors hover:bg-accent/60 active:bg-accent"
                            >
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-baseline justify-between gap-3">
                                        <p className="truncate text-lg font-medium">
                                            {orchard.name}
                                        </p>
                                        {orchard.current_season && (
                                            <p className="shrink-0 text-lg font-medium">
                                                {formatNumber(
                                                    orchard.current_yield_kg,
                                                )}{' '}
                                                <span className="text-sm font-normal text-muted-foreground">
                                                    กก.
                                                </span>
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex items-baseline justify-between gap-3 text-sm text-muted-foreground">
                                        <p className="truncate">
                                            {[
                                                orchard.location,
                                                `${formatNumber(orchard.trees_count, 0)} ต้น`,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                        <p className="shrink-0">
                                            {orchard.current_season
                                                ? `ผลผลิต${orchard.current_season}`
                                                : 'ยังไม่เปิดรอบ'}
                                        </p>
                                    </div>
                                </div>
                                <ChevronRight className="size-5 shrink-0 text-muted-foreground" />
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

OrchardsIndex.layout = {
    breadcrumbs: [{ title: 'สวน', href: index() }],
};
