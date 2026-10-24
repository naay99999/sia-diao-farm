import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { PageContainer } from '@/components/page-container';

type OverviewProps = {
    orchards: unknown[];
    dueIssues: unknown[];
    openIssues: unknown[];
    recentAiRecords: unknown[];
};

export default function Overview({ orchards }: OverviewProps) {
    return (
        <PageContainer>
            <Head title="ภาพรวม" />
            <Heading title="ภาพรวม" description={`${orchards.length} สวน`} />
        </PageContainer>
    );
}
