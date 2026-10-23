import { Head, Link, router } from '@inertiajs/react';
import { Check, ChevronRight, Copy, TriangleAlert } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import AiConnectionController from '@/actions/App/Http/Controllers/Settings/AiConnectionController';
import { useConfirm } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/farm/empty-state';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatThaiDate } from '@/lib/thai-date';
import { edit } from '@/routes/ai-connection';

type Connection = {
    clientId: string;
    name: string;
    connectedOn: string;
};

type AiRecord = {
    kind: string;
    title: string;
    subject: string;
    recordedOn: string;
    href: string | null;
};

function CopyableUrl({ url }: { url: string }) {
    const input = useRef<HTMLInputElement>(null);
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!copied) {
            return;
        }

        const timer = window.setTimeout(() => setCopied(false), 2000);

        return () => window.clearTimeout(timer);
    }, [copied]);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(url);
            setCopied(true);
        } catch {
            // No clipboard access (plain http on a phone): select the text so it can be copied by hand.
            input.current?.select();
        }
    };

    return (
        <div className="flex flex-col gap-2 sm:flex-row">
            <label htmlFor="mcp-url" className="sr-only">
                ที่อยู่สำหรับเชื่อมต่อ
            </label>
            <Input
                id="mcp-url"
                ref={input}
                readOnly
                value={url}
                onFocus={(event) => event.currentTarget.select()}
                className="min-w-0 flex-1 bg-muted/40"
            />
            <Button type="button" onClick={copy} className="shrink-0">
                {copied ? <Check /> : <Copy />}
                {copied ? 'คัดลอกแล้ว' : 'คัดลอก'}
            </Button>
        </div>
    );
}

export default function AiConnection({
    mcpUrl,
    isPubliclyReachable,
    connections,
    recentAiRecords,
}: {
    mcpUrl: string;
    isPubliclyReachable: boolean;
    connections: Connection[];
    recentAiRecords: AiRecord[];
}) {
    const confirm = useConfirm();

    const disconnect = async (connection: Connection) => {
        const confirmed = await confirm({
            title: `ยกเลิกการเชื่อมกับ ${connection.name}?`,
            description: `${connection.name} จะอ่านและบันทึกข้อมูลสวนไม่ได้อีก จนกว่าจะเชื่อมต่อและกดอนุญาตใหม่`,
            confirmLabel: 'ยกเลิกการเชื่อม',
            destructive: true,
        });

        if (confirmed) {
            router.delete(
                AiConnectionController.destroy.url(connection.clientId),
                { preserveScroll: true },
            );
        }
    };

    return (
        <>
            <Head title="เชื่อมต่อ Claude" />

            <div className="grid grid-cols-1 gap-12 lg:grid-cols-2 lg:gap-x-16">
                <div className="space-y-12">
                    <div className="space-y-4">
                        <Heading
                            variant="small"
                            title="ที่อยู่สำหรับเชื่อมต่อ"
                            description={
                                connections.length > 0
                                    ? `เชื่อมอยู่ ${connections.length.toLocaleString('th-TH')} แอป · Claude อ่านและบันทึกข้อมูลสวนผ่านที่อยู่นี้`
                                    : 'ยังไม่ได้เชื่อม · วางที่อยู่นี้ใน Claude เพื่อให้ช่วยอ่านและบันทึกข้อมูลสวน'
                            }
                        />

                        <CopyableUrl url={mcpUrl} />

                        {!isPubliclyReachable && (
                            <Alert>
                                <TriangleAlert />
                                <AlertTitle>
                                    Claude ยังเข้าที่อยู่นี้ไม่ได้
                                </AlertTitle>
                                <AlertDescription>
                                    ตอนนี้แอปเปิดอยู่บนเครื่องนี้เท่านั้น
                                    ต้องเปิดแอปบนโดเมนที่ขึ้นต้นด้วย https
                                    และเข้าจากอินเทอร์เน็ตได้ก่อน
                                    ที่อยู่ด้านบนจะเปลี่ยนตามเอง
                                </AlertDescription>
                            </Alert>
                        )}
                    </div>

                    <div className="space-y-4">
                        <Heading
                            variant="small"
                            title="วิธีเชื่อมต่อ"
                            description="ทำครั้งเดียวบนเว็บ แล้วใช้ในแอป Claude บนมือถือได้ด้วย"
                        />

                        <ol className="list-decimal space-y-3 pl-6 text-lg marker:font-medium marker:text-muted-foreground md:text-base">
                            <li>
                                เปิด{' '}
                                <span className="font-medium">claude.ai</span>{' '}
                                แล้วไปที่ ตั้งค่า →{' '}
                                <span className="font-medium">Connectors</span>
                            </li>
                            <li>
                                กด{' '}
                                <span className="font-medium">
                                    Add custom connector
                                </span>{' '}
                                ตั้งชื่อว่า เสี่ยเดียวฟาร์ม แล้ววางที่อยู่ด้านบน
                            </li>
                            <li>
                                Claude จะพามาหน้าเข้าสู่ระบบของแอปนี้
                                ล็อกอินแล้วกด{' '}
                                <span className="font-medium">อนุญาต</span>
                            </li>
                            <li>
                                ลองถาม Claude ว่า “วันนี้ต้นไหนต้องดูซ้ำบ้าง”
                            </li>
                        </ol>

                        <p className="rounded-lg bg-muted px-4 py-3 text-base md:text-sm">
                            ก่อนบันทึก แก้ หรือลบข้อมูล Claude
                            จะสรุปให้คุณยืนยันในแชตทุกครั้ง และทุกบันทึกจาก
                            Claude มีป้าย AI ในหน้าต้น
                        </p>
                    </div>
                </div>

                <div className="space-y-12">
                    <section
                        className="space-y-4"
                        aria-labelledby="connections-heading"
                    >
                        <header>
                            <h2
                                id="connections-heading"
                                className="mb-0.5 text-xl font-semibold md:text-lg"
                            >
                                แอปที่เชื่อมอยู่
                            </h2>
                            <p className="text-base text-muted-foreground md:text-sm">
                                ยกเลิกได้ทุกเมื่อ แอปจะต้องขออนุญาตใหม่
                            </p>
                        </header>

                        {connections.length === 0 ? (
                            <EmptyState>
                                ยังไม่มีแอปที่เชื่อมอยู่
                                ทำตามวิธีด้านบนเพื่อเชื่อม Claude
                            </EmptyState>
                        ) : (
                            <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                                {connections.map((connection) => (
                                    <li
                                        key={connection.clientId}
                                        className="flex min-h-12 items-center gap-3 px-4 py-3"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-lg font-medium md:text-base">
                                                {connection.name}
                                            </p>
                                            <p className="text-base text-muted-foreground md:text-sm">
                                                เชื่อมเมื่อ{' '}
                                                {formatThaiDate(
                                                    connection.connectedOn,
                                                )}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            className="shrink-0 text-destructive hover:text-destructive"
                                            onClick={() =>
                                                disconnect(connection)
                                            }
                                        >
                                            ยกเลิกการเชื่อม
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section
                        className="space-y-4"
                        aria-labelledby="ai-records-heading"
                    >
                        <header>
                            <h2
                                id="ai-records-heading"
                                className="mb-0.5 text-xl font-semibold md:text-lg"
                            >
                                AI บันทึกล่าสุด
                            </h2>
                            <p className="text-base text-muted-foreground md:text-sm">
                                สิ่งที่ Claude บันทึกให้ 5 รายการล่าสุด
                            </p>
                        </header>

                        {recentAiRecords.length === 0 ? (
                            <EmptyState>ยังไม่มีบันทึกจาก AI</EmptyState>
                        ) : (
                            <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                                {recentAiRecords.map((record, index) => {
                                    const content = (
                                        <>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-lg font-medium md:text-base">
                                                    {record.title}
                                                </p>
                                                <p className="truncate text-base text-muted-foreground md:text-sm">
                                                    {record.kind} ·{' '}
                                                    {record.subject}
                                                </p>
                                            </div>
                                            <span className="shrink-0 text-base text-muted-foreground md:text-sm">
                                                {formatThaiDate(
                                                    record.recordedOn,
                                                )}
                                            </span>
                                        </>
                                    );

                                    return (
                                        <li key={index}>
                                            {record.href ? (
                                                <Link
                                                    href={record.href}
                                                    className="flex min-h-12 items-center gap-3 px-4 py-3 transition-colors hover:bg-accent/60 active:bg-accent"
                                                >
                                                    {content}
                                                    <ChevronRight className="size-5 shrink-0 text-muted-foreground" />
                                                </Link>
                                            ) : (
                                                <div className="flex min-h-12 items-center gap-3 px-4 py-3">
                                                    {content}
                                                    <span className="size-5 shrink-0" />
                                                </div>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

AiConnection.layout = {
    breadcrumbs: [
        {
            title: 'เชื่อมต่อ Claude',
            href: edit(),
        },
    ],
};
