import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/react';
import { Check, ChevronRight, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import AssistantController from '@/actions/App/Http/Controllers/AssistantController';
import { ExamplePrompts } from '@/components/assistant/example-prompts';
import { useConfirm } from '@/components/confirm-dialog';
import { CopyableUrl } from '@/components/copyable-url';
import { EmptyState } from '@/components/farm/empty-state';
import { SectionTabs } from '@/components/farm/section-tabs';
import Heading from '@/components/heading';
import { PageContainer } from '@/components/page-container';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { formatThaiDate } from '@/lib/thai-date';
import { index } from '@/routes/assistant';

type Connection = {
    clientId: string;
    name: string;
    connectedOn: string;
};

type Props = {
    myConnections: Connection[];
    recentAiRecords: {
        kind: string;
        title: string;
        subject: string;
        recordedOn: string;
        href: string | null;
        by: string | null;
    }[];
    setup: { mcpUrl: string; isPubliclyReachable: boolean } | null;
    members:
        | { userId: number; name: string; connections: Connection[] }[]
        | null;
};

const claudeDoes = [
    'สรุปให้ยืนยันก่อนบันทึก แก้ หรือลบทุกครั้ง',
    'ใส่ป้าย “AI · ชื่อคุณ” ในหน้าต้น',
    'ถ้าลบผิด จะบอกให้ว่ากู้คืนได้',
    'ไม่เดาอัตราผสมยา จะให้ดูฉลากหรือถามเจ้าหน้าที่เกษตร',
];

const stillInApp = [
    'แนบรูปเข้าบันทึก (Claude ดูรูปในแชตได้ แต่เก็บรูปเข้าระบบไม่ได้)',
    'เพิ่มหรือแก้สวน ต้น ฤดูกาล และพิมพ์ QR',
];

type AssistantTab = 'guide' | 'settings';

const assistantTabs: AssistantTab[] = ['guide', 'settings'];

const TAB_STORAGE_KEY = 'assistant-tab';

function rememberedTab(): AssistantTab {
    try {
        const stored = window.sessionStorage.getItem(TAB_STORAGE_KEY);

        return assistantTabs.includes(stored as AssistantTab)
            ? (stored as AssistantTab)
            : 'guide';
    } catch {
        return 'guide';
    }
}

function rememberTab(tab: AssistantTab): void {
    try {
        window.sessionStorage.setItem(TAB_STORAGE_KEY, tab);
    } catch {
        // Private mode or blocked storage: the tab simply resets on the next visit.
    }
}

function Section({
    id,
    title,
    description,
    children,
}: {
    id: string;
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-4" aria-labelledby={id}>
            <header>
                <h2 id={id} className="mb-0.5 text-xl font-semibold md:text-lg">
                    {title}
                </h2>
                {description && (
                    <p className="text-base text-muted-foreground md:text-sm">
                        {description}
                    </p>
                )}
            </header>
            {children}
        </section>
    );
}

function BulletList({ items }: { items: string[] }) {
    return (
        <ul className="list-disc space-y-2 pl-6 text-lg marker:text-muted-foreground md:text-base">
            {items.map((item) => (
                <li key={item}>{item}</li>
            ))}
        </ul>
    );
}

export default function Assistant({
    myConnections,
    recentAiRecords,
    setup,
    members,
}: Props) {
    const confirm = useConfirm();
    const { auth } = usePage().props;
    const [tab, setTab] = useState<AssistantTab>(rememberedTab);

    const selectTab = (next: AssistantTab) => {
        setTab(next);
        rememberTab(next);
    };

    setLayoutProps({
        breadcrumbs: [{ title: 'ผู้ช่วย AI', href: index() }],
    });

    const disconnect = async (
        userId: number,
        ownerName: string | null,
        connection: Connection,
    ) => {
        const confirmed = await confirm({
            title: `ยกเลิกการเชื่อมกับ ${connection.name}?`,
            description: ownerName
                ? `${ownerName} จะอ่านและบันทึกข้อมูลสวนผ่าน ${connection.name} ไม่ได้อีก จนกว่าจะเชื่อมต่อและกดอนุญาตใหม่`
                : `${connection.name} จะอ่านและบันทึกข้อมูลสวนไม่ได้อีก จนกว่าจะเชื่อมต่อและกดอนุญาตใหม่`,
            confirmLabel: 'ยกเลิกการเชื่อม',
            destructive: true,
        });

        if (confirmed) {
            router.delete(
                AssistantController.destroy.url({
                    user: userId,
                    client: connection.clientId,
                }),
                { preserveScroll: true },
            );
        }
    };

    const isConnected = myConnections.length > 0;

    return (
        <PageContainer>
            <Head title="ผู้ช่วย AI" />

            <Heading
                title="ผู้ช่วย AI"
                description="คุยกับ Claude เพื่อถามและบันทึกข้อมูลสวน ไม่ต้องเปิดแอปนี้"
            />

            <div className="space-y-6">
                <SectionTabs
                    label="หัวข้อผู้ช่วย AI"
                    value={tab}
                    onChange={selectTab}
                    tabs={[
                        { value: 'guide', label: 'วิธีใช้งาน' },
                        { value: 'settings', label: 'การเชื่อมต่อ' },
                    ]}
                />

                {tab === 'guide' && (
                    <div
                        id="panel-guide"
                        role="tabpanel"
                        aria-labelledby="tab-guide"
                        className="grid grid-cols-1 gap-12 lg:grid-cols-2 lg:gap-x-16"
                    >
                        <div className="space-y-12">
                            <Section
                                id="examples-heading"
                                title="ลองพิมพ์ใน Claude"
                                description="กดที่ประโยคเพื่อคัดลอก แล้ววางในแชตของ Claude ได้เลย"
                            >
                                <ExamplePrompts />
                            </Section>
                        </div>

                        <div className="space-y-12">
                            <Section id="does-heading" title="Claude จะ…">
                                <BulletList items={claudeDoes} />
                            </Section>

                            <Section id="in-app-heading" title="ยังต้องทำในแอป">
                                <BulletList items={stillInApp} />
                            </Section>
                        </div>
                    </div>
                )}

                {tab === 'settings' && (
                    <div
                        id="panel-settings"
                        role="tabpanel"
                        aria-labelledby="tab-settings"
                        className="grid grid-cols-1 gap-12 lg:grid-cols-2 lg:gap-x-16"
                    >
                        <div className="space-y-12">
                            <section
                                aria-labelledby="status-heading"
                                className="rounded-lg border bg-card"
                            >
                                <h2 id="status-heading" className="sr-only">
                                    สถานะการเชื่อมต่อ
                                </h2>
                                {isConnected ? (
                                    <div>
                                        <div className="flex min-h-12 items-center gap-3 px-4 py-3">
                                            <Check
                                                className="size-5 shrink-0 text-primary"
                                                aria-hidden
                                            />
                                            <p className="text-lg font-medium md:text-base">
                                                Claude ของคุณเชื่อมอยู่
                                            </p>
                                        </div>
                                        <ul className="divide-y border-t">
                                            {myConnections.map((connection) => (
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
                                                            disconnect(
                                                                auth.user.id,
                                                                null,
                                                                connection,
                                                            )
                                                        }
                                                    >
                                                        ยกเลิกการเชื่อม
                                                    </Button>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                ) : (
                                    <p className="px-4 py-3 text-lg md:text-base">
                                        {auth.user.is_owner
                                            ? 'ยังไม่ได้เชื่อม Claude · ทำตามขั้นตอนด้านล่างเพื่อเชื่อมบัญชีของคุณ'
                                            : 'ยังไม่ได้เชื่อม Claude · ขอให้เจ้าของสวนช่วยตั้งค่าให้'}
                                    </p>
                                )}
                            </section>

                            {setup !== null && (
                                <Section
                                    id="setup-heading"
                                    title="ตั้งค่าให้คนในครอบครัว"
                                    description="ทำครั้งเดียวต่อคน แล้วใช้ในแอป Claude บนมือถือของเขาได้ด้วย"
                                >
                                    <CopyableUrl url={setup.mcpUrl} />

                                    {!setup.isPubliclyReachable && (
                                        <Alert>
                                            <TriangleAlert />
                                            <AlertTitle>
                                                Claude ยังเข้าที่อยู่นี้ไม่ได้
                                            </AlertTitle>
                                            <AlertDescription>
                                                ตอนนี้แอปเปิดอยู่บนเครื่องนี้เท่านั้น
                                                ต้องเปิดแอปบนโดเมนที่ขึ้นต้นด้วย
                                                https
                                                และเข้าจากอินเทอร์เน็ตได้ก่อน
                                                ที่อยู่ด้านบนจะเปลี่ยนตามเอง
                                            </AlertDescription>
                                        </Alert>
                                    )}

                                    <ol className="list-decimal space-y-3 pl-6 text-lg marker:font-medium marker:text-muted-foreground md:text-base">
                                        <li>
                                            ล็อกอิน{' '}
                                            <span className="font-medium">
                                                claude.ai
                                            </span>{' '}
                                            ด้วยบัญชี Claude ของคนนั้น แล้วไปที่
                                            ตั้งค่า →{' '}
                                            <span className="font-medium">
                                                Connectors
                                            </span>
                                        </li>
                                        <li>
                                            กด{' '}
                                            <span className="font-medium">
                                                Add custom connector
                                            </span>{' '}
                                            ตั้งชื่อว่า เสี่ยเดียวฟาร์ม
                                            แล้ววางที่อยู่ด้านบน
                                        </li>
                                        <li>
                                            Claude
                                            จะพามาหน้าเข้าสู่ระบบของแอปนี้{' '}
                                            <span className="font-medium">
                                                ล็อกอินแอปนี้ด้วยบัญชีของคนนั้น
                                                แล้วกดอนุญาต
                                            </span>
                                        </li>
                                        <li>
                                            ลองถาม Claude ว่า
                                            “วันนี้ต้นไหนต้องดูซ้ำบ้าง”
                                        </li>
                                    </ol>
                                </Section>
                            )}
                        </div>

                        <div className="space-y-12">
                            {members !== null && (
                                <Section
                                    id="members-heading"
                                    title="การเชื่อมต่อของทุกคน"
                                    description="ยกเลิกได้ทุกเมื่อ คนนั้นจะต้องกดอนุญาตใหม่"
                                >
                                    <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                                        {members.map((member) =>
                                            member.connections.length === 0 ? (
                                                <li
                                                    key={member.userId}
                                                    className="flex min-h-12 items-center justify-between gap-3 px-4 py-3"
                                                >
                                                    <span className="min-w-0 truncate text-lg font-medium md:text-base">
                                                        {member.name}
                                                    </span>
                                                    <span className="shrink-0 text-base text-muted-foreground md:text-sm">
                                                        ยังไม่เชื่อม
                                                    </span>
                                                </li>
                                            ) : (
                                                member.connections.map(
                                                    (connection) => (
                                                        <li
                                                            key={`${member.userId}-${connection.clientId}`}
                                                            className="flex min-h-12 items-center gap-3 px-4 py-3"
                                                        >
                                                            <div className="min-w-0 flex-1">
                                                                <p className="truncate text-lg font-medium md:text-base">
                                                                    {
                                                                        member.name
                                                                    }{' '}
                                                                    <span className="font-normal text-muted-foreground">
                                                                        ·{' '}
                                                                        {
                                                                            connection.name
                                                                        }
                                                                    </span>
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
                                                                    disconnect(
                                                                        member.userId,
                                                                        member.name,
                                                                        connection,
                                                                    )
                                                                }
                                                            >
                                                                ยกเลิกการเชื่อม
                                                            </Button>
                                                        </li>
                                                    ),
                                                )
                                            ),
                                        )}
                                    </ul>
                                </Section>
                            )}

                            <Section
                                id="ai-records-heading"
                                title="บันทึกล่าสุดจาก AI"
                                description="สิ่งที่ Claude บันทึกให้ทุกคน 5 รายการล่าสุด"
                            >
                                {recentAiRecords.length === 0 ? (
                                    <EmptyState>
                                        ยังไม่มีบันทึกจาก AI
                                    </EmptyState>
                                ) : (
                                    <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                                        {recentAiRecords.map(
                                            (record, position) => {
                                                const content = (
                                                    <>
                                                        <div className="min-w-0 flex-1">
                                                            <p className="truncate text-lg font-medium md:text-base">
                                                                {record.title}
                                                            </p>
                                                            <p className="truncate text-base text-muted-foreground md:text-sm">
                                                                {[
                                                                    record.kind,
                                                                    record.subject,
                                                                    record.by,
                                                                ]
                                                                    .filter(
                                                                        Boolean,
                                                                    )
                                                                    .join(
                                                                        ' · ',
                                                                    )}
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
                                                    <li key={position}>
                                                        {record.href ? (
                                                            <Link
                                                                href={
                                                                    record.href
                                                                }
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
                                            },
                                        )}
                                    </ul>
                                )}
                            </Section>
                        </div>
                    </div>
                )}
            </div>
        </PageContainer>
    );
}
