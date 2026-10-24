import { Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { EmptyState } from '@/components/farm/empty-state';
import { OrchardSummaryCard } from '@/components/farm/orchard-summary-card';
import Heading from '@/components/heading';
import { PageContainer } from '@/components/page-container';
import { Badge } from '@/components/ui/badge';
import { formatThaiDate } from '@/lib/thai-date';
import { show as showTree } from '@/routes/trees';
import type {
    FarmIssueSummary,
    OrchardOverview,
    RecentAiRecord,
} from '@/types/farm';

type OverviewProps = {
    orchards: OrchardOverview[];
    dueIssues: FarmIssueSummary[];
    openIssues: FarmIssueSummary[];
    recentAiRecords: RecentAiRecord[];
};

function IssueRows({
    title,
    issues,
    emptyText,
}: {
    title: string;
    issues: FarmIssueSummary[];
    emptyText: string;
}) {
    return (
        <section className="space-y-2">
            <h2 className="text-xl font-semibold md:text-lg">
                {title}{' '}
                <span className="text-base font-normal text-muted-foreground md:text-sm">
                    {issues.length.toLocaleString('th-TH')}
                </span>
            </h2>
            {issues.length === 0 ? (
                <EmptyState>{emptyText}</EmptyState>
            ) : (
                <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                    {issues.map((issue) => (
                        <li key={issue.id}>
                            <Link
                                href={showTree(issue.treeId)}
                                className="flex min-h-12 items-center gap-3 px-4 py-3 transition-colors hover:bg-accent/60 active:bg-accent"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="text-lg font-medium md:text-base">
                                        {issue.orchardName} · {issue.treeCode}
                                    </p>
                                    <p className="text-base md:text-sm">
                                        {issue.finding}
                                    </p>
                                    <p className="text-base text-muted-foreground md:text-sm">
                                        ดูซ้ำ {formatThaiDate(issue.followUpOn)}
                                    </p>
                                </div>
                                <ChevronRight
                                    aria-hidden="true"
                                    className="size-5 shrink-0 text-muted-foreground"
                                />
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function RecentRecords({ records }: { records: RecentAiRecord[] }) {
    return (
        <section className="space-y-2">
            <h2 className="text-xl font-semibold md:text-lg">
                บันทึกล่าสุดจาก Claude
            </h2>
            {records.length === 0 ? (
                <EmptyState>ยังไม่มีบันทึกจาก Claude</EmptyState>
            ) : (
                <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                    {records.map((record, index) => {
                        const content = (
                            <div className="min-w-0 flex-1">
                                <p className="flex flex-wrap items-center gap-x-2 text-lg font-medium md:text-base">
                                    {record.title}
                                    <Badge variant="outline">
                                        <span className="sr-only">
                                            บันทึกโดย{' '}
                                        </span>
                                        AI{record.by && ` · ${record.by}`}
                                    </Badge>
                                </p>
                                <p className="text-base text-muted-foreground md:text-sm">
                                    {record.kind} · {record.subject}
                                    {record.recordedOn &&
                                        ` · ${formatThaiDate(record.recordedOn)}`}
                                </p>
                            </div>
                        );

                        return (
                            <li key={index}>
                                {record.href ? (
                                    <Link
                                        href={record.href}
                                        className="flex min-h-12 items-center gap-3 px-4 py-3 transition-colors hover:bg-accent/60 active:bg-accent"
                                    >
                                        {content}
                                        <ChevronRight
                                            aria-hidden="true"
                                            className="size-5 shrink-0 text-muted-foreground"
                                        />
                                    </Link>
                                ) : (
                                    <div className="flex min-h-12 items-center gap-3 px-4 py-3">
                                        {content}
                                    </div>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}

export default function Overview({
    orchards,
    dueIssues,
    openIssues,
    recentAiRecords,
}: OverviewProps) {
    const dueIds = new Set(dueIssues.map((issue) => issue.id));
    const otherOpenIssues = openIssues.filter((issue) => !dueIds.has(issue.id));

    return (
        <PageContainer>
            <Head title="ภาพรวม" />
            <Heading
                title="ภาพรวม"
                description={`${orchards.length.toLocaleString('th-TH')} สวน`}
            />

            {orchards.length === 0 ? (
                <EmptyState>ยังไม่มีสวน เพิ่มสวนแรกจากหน้าสวน</EmptyState>
            ) : (
                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <div className="min-w-0 space-y-6">
                        {orchards.map((orchard) => (
                            <OrchardSummaryCard
                                key={orchard.id}
                                orchard={orchard}
                            />
                        ))}
                    </div>
                    <div className="min-w-0 space-y-6 lg:sticky lg:top-6">
                        <IssueRows
                            title="ต้องดูซ้ำ"
                            issues={dueIssues}
                            emptyText="ไม่มีต้นที่ถึงวันดูซ้ำ"
                        />
                        <IssueRows
                            title="ปัญหาค้าง"
                            issues={otherOpenIssues}
                            emptyText="ไม่มีปัญหาค้าง"
                        />
                        <RecentRecords records={recentAiRecords} />
                    </div>
                </div>
            )}
        </PageContainer>
    );
}
