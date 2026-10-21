import { Form, Head, setLayoutProps } from '@inertiajs/react';
import CareRecordController from '@/actions/App/Http/Controllers/Farm/CareRecordController';
import {
    CareRecordFields,
    TreePicker,
} from '@/components/farm/care-record-form';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/orchards';
import type { Orchard, Tree } from '@/types/farm';

export default function CareRecordCreate({
    orchard,
    trees,
}: {
    orchard: Orchard;
    trees: Tree[];
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'สวน', href: index() },
            { title: orchard.name, href: show(orchard.id) },
            { title: 'บันทึกการดูแลหลายต้น', href: '#' },
        ],
    });

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="บันทึกการดูแลหลายต้น" />
            <Heading
                title="บันทึกการดูแลหลายต้น"
                description={orchard.name}
            />
            {trees.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    ยังไม่มีต้นไม้ในสวนนี้
                </p>
            ) : (
                <Form
                    {...CareRecordController.store.form(orchard.id)}
                    className="max-w-2xl space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <CareRecordFields errors={errors} />
                            <TreePicker
                                trees={trees}
                                initialSelected={trees.map((tree) => tree.id)}
                                errors={errors}
                            />
                            <Button
                                type="submit"
                                disabled={processing}
                                className="h-12 w-full text-base"
                            >
                                บันทึก
                            </Button>
                        </>
                    )}
                </Form>
            )}
        </div>
    );
}
