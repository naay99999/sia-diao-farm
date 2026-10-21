import { Form, Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import OrchardController from '@/actions/App/Http/Controllers/Farm/OrchardController';
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
import { index, show } from '@/routes/orchards';
import type { Orchard } from '@/types/farm';

export default function OrchardShow({ orchard }: { orchard: Orchard }) {
    const [editing, setEditing] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: 'สวน', href: index() },
            { title: orchard.name, href: show(orchard.id) },
        ],
    });

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title={orchard.name} />
            <div className="flex items-center justify-between gap-2">
                <Heading title={orchard.name} description={orchard.location ?? undefined} />
                <div className="flex gap-2">
                    <Dialog open={editing} onOpenChange={setEditing}>
                        <DialogTrigger asChild>
                            <Button variant="outline">แก้ไข</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>แก้ไขสวน</DialogTitle>
                                <DialogDescription>แก้ไขข้อมูลสวน</DialogDescription>
                            </DialogHeader>
                            <Form
                                {...OrchardController.update.form(orchard.id)}
                                onSuccess={() => setEditing(false)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <OrchardFormFields orchard={orchard} errors={errors} />
                                        <Button type="submit" disabled={processing}>
                                            บันทึก
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                    <Button
                        variant="destructive"
                        onClick={() => {
                            if (confirm('ลบสวนนี้และข้อมูลทั้งหมดในสวน?')) {
                                router.delete(OrchardController.destroy.url(orchard.id));
                            }
                        }}
                    >
                        ลบ
                    </Button>
                </div>
            </div>

            {orchard.notes && <p className="text-sm">{orchard.notes}</p>}
        </div>
    );
}
