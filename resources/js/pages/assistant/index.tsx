import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { ExamplePrompts } from '@/components/assistant/example-prompts';
import { EmptyState } from '@/components/farm/empty-state';
import Heading from '@/components/heading';
import { PageContainer } from '@/components/page-container';
import { formatThaiDate } from '@/lib/thai-date';
import { index as aiConnectionsIndex } from '@/routes/ai-connections';
import { index } from '@/routes/assistant';

type Props = {
    recentAiRecords: {
        kind: string;
        title: string;
        subject: string;
        recordedOn: string;
        href: string | null;
        by: string | null;
    }[];
};

const claudeDoes = [
    'สรุปให้ยืนยันก่อนบันทึก แก้ หรือลบทุกครั้ง',
    'ใส่ป้าย “AI · ชื่อคุณ” ในหน้าต้น',
    'ถ้าลบผิด จะบอกให้ว่ากู้คืนได้',
    'ไม่เดาอัตราผสมยา จะให้ดูฉลากหรือถามเจ้าหน้าที่เกษตร',
];

const stillInApp = [
    'แนบรูปเข้าบันทึก (Claude ดูรูปในแชตได้ แต่เก็บรูปเข้าระบบไม่ได้)',
    'ลบสวน ต้น หรือฤดูกาล และพิมพ์ QR',
];

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

export default function Assistant({ recentAiRecords }: Props) {
    setLayoutProps({
        breadcrumbs: [{ title: 'ผู้ช่วย AI', href: index() }],
    });

    return (
        <PageContainer>
            <Head title="ผู้ช่วย AI" />

            <Heading
                title="ผู้ช่วย AI"
                description="คุยกับ Claude เพื่อถามและบันทึกข้อมูลสวน ไม่ต้องเปิดแอปนี้"
            />

            <p className="text-lg md:text-base">
                <Link
                    href={aiConnectionsIndex()}
                    className="font-medium text-primary underline-offset-4 hover:underline"
                >
                    ตั้งค่าการเชื่อมต่อ Claude ได้ที่หน้าตั้งค่า
                </Link>
            </p>

            <div className="grid grid-cols-1 gap-12 lg:grid-cols-2 lg:gap-x-16">
                <div className="space-y-12">
                    <Section
                        id="examples-heading"
                        title="ลองพิมพ์ใน Claude"
                        description="กดที่ประโยคเพื่อคัดลอก แล้ววางในแชตของ Claude ได้เลย"
                    >
                        <ExamplePrompts />
                    </Section>

                    <Section
                        id="ai-records-heading"
                        title="บันทึกล่าสุดจาก AI"
                        description="สิ่งที่ Claude บันทึกให้ทุกคน 5 รายการล่าสุด"
                    >
                        {recentAiRecords.length === 0 ? (
                            <EmptyState>ยังไม่มีบันทึกจาก AI</EmptyState>
                        ) : (
                            <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                                {recentAiRecords.map((record, position) => {
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
                                                        .filter(Boolean)
                                                        .join(' · ')}
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
        </PageContainer>
    );
}
