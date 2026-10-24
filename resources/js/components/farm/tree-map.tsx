import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { show as showTree } from '@/routes/trees';
import type { TreeMapRow, TreeMapTree, TreeStatus } from '@/types/farm';

export const treeStatusLabels: Record<TreeStatus, string> = {
    due: 'ถึงวันดูซ้ำ',
    issue: 'มีปัญหาค้าง',
    normal: 'ปกติ',
};

/**
 * Status reads from the green ladder, never red: due is the strongest fill, an open issue is a soft fill with a dark ring.
 */
const treeStatusClasses: Record<TreeStatus, string> = {
    due: 'bg-primary',
    issue: 'bg-brand-soft ring-2 ring-inset ring-brand-ink',
    normal: 'bg-muted',
};

const statusOrder: TreeStatus[] = ['due', 'issue', 'normal'];

/**
 * Columns follow `position`; trees without one, or sharing one, take the next free column.
 */
function placeTrees(
    trees: TreeMapTree[],
): { tree: TreeMapTree; column: number }[] {
    let lastColumn = 0;

    return trees.map((tree) => {
        lastColumn = Math.max(tree.position ?? 0, lastColumn + 1);

        return { tree, column: lastColumn };
    });
}

/**
 * Draws an orchard's trees in rows. Each tree links to its page; drawing only, nothing is fetched.
 */
export function TreeMap({ rows }: { rows: TreeMapRow[] }) {
    const statusCounts: Record<TreeStatus, number> = {
        due: 0,
        issue: 0,
        normal: 0,
    };

    for (const row of rows) {
        for (const tree of row.trees) {
            statusCounts[tree.status]++;
        }
    }

    const placedRows = rows.map((row) => ({
        row: row.row,
        placed: placeTrees(row.trees),
    }));

    return (
        <div className="space-y-3">
            <div className="overflow-x-auto rounded-lg border bg-card p-3">
                <div className="flex w-max min-w-full flex-col gap-2">
                    {placedRows.map(({ row, placed }) => (
                        <div
                            key={row ?? 'other'}
                            className="flex items-center gap-3"
                        >
                            <span className="w-12 shrink-0 text-base font-medium md:text-sm">
                                {row ?? 'อื่นๆ'}
                            </span>
                            <div
                                className="grid gap-1.5"
                                style={{
                                    gridTemplateColumns:
                                        'repeat(var(--cols), 2rem)',
                                    ['--cols' as string]: Math.max(
                                        0,
                                        ...placed.map(({ column }) => column),
                                    ),
                                }}
                            >
                                {placed.map(({ tree, column }) => (
                                    <Link
                                        key={tree.id}
                                        href={showTree(tree.id)}
                                        title={`ต้น ${tree.code} ${treeStatusLabels[tree.status]}`}
                                        aria-label={`ต้น ${tree.code} ${treeStatusLabels[tree.status]}`}
                                        style={{ gridColumnStart: column }}
                                        className={cn(
                                            'size-8 rounded-full outline-offset-2 transition-opacity hover:opacity-80 focus-visible:outline-2 focus-visible:outline-ring',
                                            treeStatusClasses[tree.status],
                                        )}
                                    />
                                ))}
                            </div>
                        </div>
                    ))}
                    {placedRows.length === 0 && (
                        <p className="text-base text-muted-foreground md:text-sm">
                            ยังไม่มีต้นไม้ในสวนนี้
                        </p>
                    )}
                </div>
            </div>
            <ul className="flex flex-wrap gap-x-4 gap-y-1 text-base md:text-sm">
                {statusOrder.map((status) => (
                    <li key={status} className="flex items-center gap-2">
                        <span
                            aria-hidden="true"
                            className={cn(
                                'size-4 rounded-full',
                                treeStatusClasses[status],
                            )}
                        />
                        {treeStatusLabels[status]}{' '}
                        <span className="tabular-nums">
                            {statusCounts[status].toLocaleString('th-TH')}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
