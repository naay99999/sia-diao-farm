import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import OrchardController from '@/actions/App/Http/Controllers/Farm/OrchardController';
import Heading from '@/components/heading';
import OrchardFormFields from '@/components/orchard-form-fields';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { index, show } from '@/routes/orchards';
import type { OrchardSummary } from '@/types/farm';

export default function OrchardsIndex({ orchards }: { orchards: OrchardSummary[] }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="สวน" />
            <div className="flex items-center justify-between">
                <Heading title="สวน" description="สวนทั้งหมดของคุณ" />
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogTrigger asChild>
                        <Button>เพิ่มสวน</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>เพิ่มสวน</DialogTitle>
                            <DialogDescription>กรอกข้อมูลสวนใหม่</DialogDescription>
                        </DialogHeader>
                        <Form {...OrchardController.store.form()} className="space-y-4">
                            {({ processing, errors }) => (
                                <>
                                    <OrchardFormFields errors={errors} />
                                    <Button type="submit" disabled={processing}>
                                        บันทึก
                                    </Button>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>

            <div className="grid gap-3 md:grid-cols-2">
                {orchards.length === 0 && (
                    <p className="text-muted-foreground text-sm">ยังไม่มีสวน — กดเพิ่มสวนเพื่อเริ่มต้น</p>
                )}
                {orchards.map((orchard) => (
                    <Link key={orchard.id} href={show(orchard.id)}>
                        <Card className="p-4 transition hover:border-foreground/20">
                            <p className="font-medium">{orchard.name}</p>
                            {orchard.location && <p className="text-muted-foreground text-sm">{orchard.location}</p>}
                            <p className="mt-2 text-sm">
                                {orchard.trees_count.toLocaleString('th-TH')} ต้น
                                {' · '}
                                {orchard.current_season ?? 'ยังไม่เปิดรอบ'}
                            </p>
                            {orchard.current_season && (
                                <p className="text-muted-foreground text-sm">
                                    ผลผลิตรอบนี้{' '}
                                    {orchard.current_yield_kg.toLocaleString('th-TH')} กก.
                                </p>
                            )}
                        </Card>
                    </Link>
                ))}
            </div>
        </div>
    );
}

OrchardsIndex.layout = {
    breadcrumbs: [{ title: 'สวน', href: index() }],
};
