import { Form, Head, setLayoutProps } from '@inertiajs/react';
import CareRecordController from '@/actions/App/Http/Controllers/Farm/CareRecordController';
import { CareRecordFormBody } from '@/components/farm/care-record-form';
import { SubmitButton } from '@/components/farm/submit-button';
import Heading from '@/components/heading';
import { index, show } from '@/routes/orchards';
import type { IssueSummary, Orchard, Tree } from '@/types/farm';

export default function CareRecordCreate({
    orchard,
    trees,
    openIssues,
}: {
    orchard: Orchard;
    trees: Tree[];
    openIssues: IssueSummary[];
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'สวน', href: index() },
            { title: orchard.name, href: show(orchard.id) },
            { title: 'บันทึกการดูแลหลายต้น', href: '#' },
        ],
    });

    return (
        <div className="flex flex-1 flex-col gap-6 px-4 pt-5 md:px-6 md:pt-6">
            <Head title="บันทึกการดูแลหลายต้น" />
            <Heading title="บันทึกการดูแลหลายต้น" description={orchard.name} />
            {trees.length === 0 ? (
                <p className="text-base text-muted-foreground md:text-sm">
                    ยังไม่มีต้นไม้ในสวนนี้
                </p>
            ) : (
                <Form
                    {...CareRecordController.store.form(orchard.id)}
                    className="max-w-2xl space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <CareRecordFormBody
                                errors={errors}
                                trees={trees}
                                initialSelected={trees.map((tree) => tree.id)}
                                openIssues={openIssues}
                            />
                            <div className="sticky bottom-[calc(4rem+env(safe-area-inset-bottom))] -mx-4 border-t bg-background px-4 py-3 md:bottom-0 md:-mx-6 md:px-6">
                                <SubmitButton processing={processing} />
                            </div>
                        </>
                    )}
                </Form>
            )}
        </div>
    );
}
