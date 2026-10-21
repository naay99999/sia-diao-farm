import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { todayIso } from '@/components/farm/care-record-form';
import { Badge } from '@/components/ui/badge';
import { formatThaiDate } from '@/lib/thai-date';
import { cn } from '@/lib/utils';
import { show as showTree } from '@/routes/trees';
import type { IssueSummary } from '@/types/farm';

function IssueGroup({
    title,
    issues,
    highlight = false,
}: {
    title: string;
    issues: IssueSummary[];
    highlight?: boolean;
}) {
    if (issues.length === 0) {
        return null;
    }

    const today = todayIso();

    return (
        <section className="space-y-2">
            <h2 className="text-xl font-semibold md:text-lg">
                {title}{' '}
                <span className="text-base font-normal text-muted-foreground md:text-sm">
                    {issues.length.toLocaleString('th-TH')}
                </span>
            </h2>
            <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                {issues.map((issue) => (
                    <li key={issue.id}>
                        <Link
                            href={showTree(issue.treeId)}
                            className={cn(
                                'flex min-h-12 items-center gap-3 px-4 py-3 transition-colors hover:bg-accent/60 active:bg-accent',
                                highlight && 'bg-brand-soft',
                            )}
                        >
                            <div className="min-w-0 flex-1">
                                <p className="flex flex-wrap items-center gap-x-2 text-lg font-medium md:text-base">
                                    <span>{issue.treeCode}</span>
                                    <span className="font-normal">
                                        {issue.finding}
                                    </span>
                                    {highlight && (
                                        <Badge>
                                            {issue.followUpOn < today
                                                ? 'เลยกำหนด'
                                                : 'ถึงกำหนดวันนี้'}
                                        </Badge>
                                    )}
                                </p>
                                <p className="text-base text-muted-foreground md:text-sm">
                                    พบเมื่อ {formatThaiDate(issue.openedOn)} ·
                                    ดูซ้ำ {formatThaiDate(issue.followUpOn)}
                                </p>
                            </div>
                            <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}

/**
 * The evening checklist of an orchard: what is due for another look, then every problem still open.
 * `openIssues` includes the due ones, so they are filtered out of the second group here.
 */
export function IssueList({
    dueIssues,
    openIssues,
}: {
    dueIssues: IssueSummary[];
    openIssues: IssueSummary[];
}) {
    const otherOpenIssues = openIssues.filter((issue) => !issue.isDue);

    if (dueIssues.length === 0 && otherOpenIssues.length === 0) {
        return null;
    }

    return (
        <div className="space-y-4">
            <IssueGroup title="ต้องดูซ้ำ" issues={dueIssues} highlight />
            <IssueGroup title="ปัญหาค้างอยู่" issues={otherOpenIssues} />
        </div>
    );
}
