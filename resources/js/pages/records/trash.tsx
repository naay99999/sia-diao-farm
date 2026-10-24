import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import { restore } from '@/actions/App/Http/Controllers/Farm/TrashController';
import { EmptyState } from '@/components/farm/empty-state';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatThaiDate } from '@/lib/thai-date';
import { trash } from '@/routes/records';
import type { TrashItem } from '@/types/farm';

export default function RecordsTrash({ items }: { items: TrashItem[] }) {
    const [restoringKey, setRestoringKey] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    setLayoutProps({
        breadcrumbs: [
            { title: 'บันทึก', href: '/records' },
            { title: 'ถังขยะ', href: trash.url() },
        ],
    });

    const restoreItem = (item: TrashItem) => {
        const key = `${item.type}-${item.id}`;

        setError(null);
        router.patch(
            restore.url({ type: item.type, id: item.id }),
            {},
            {
                preserveScroll: true,
                onStart: () => setRestoringKey(key),
                onFinish: () => setRestoringKey(null),
                onError: (errors) => setError(errors.id ?? 'กู้คืนไม่สำเร็จ'),
            },
        );
    };

    return (
        <>
            <Head title="ถังขยะ" />

            <p className="text-base text-muted-foreground md:text-sm">
                บันทึกที่ลบไปแล้วจะอยู่ที่นี่ กดกู้คืนเพื่อนำกลับมา
            </p>

            {error && (
                <Alert variant="destructive" role="alert">
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}

            {items.length === 0 ? (
                <EmptyState>ถังขยะว่างเปล่า</EmptyState>
            ) : (
                <ul className="divide-y rounded-lg border bg-card">
                    {items.map((item) => {
                        const key = `${item.type}-${item.id}`;

                        return (
                            <li
                                key={key}
                                className="flex items-center gap-3 py-3 pr-3 pl-4"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="flex flex-wrap items-center gap-x-2 text-lg font-medium md:text-base">
                                        {item.title}
                                        <Badge variant="secondary">
                                            {item.kind}
                                        </Badge>
                                    </p>
                                    <p className="text-base text-muted-foreground md:text-sm">
                                        {item.subject} · ลบเมื่อ{' '}
                                        {formatThaiDate(item.deletedAt)}
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    disabled={restoringKey === key}
                                    onClick={() => restoreItem(item)}
                                    aria-label={`กู้คืน ${item.title}`}
                                >
                                    กู้คืน
                                </Button>
                            </li>
                        );
                    })}
                </ul>
            )}
        </>
    );
}
