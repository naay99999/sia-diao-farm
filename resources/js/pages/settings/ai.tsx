import { Head, router, usePage } from '@inertiajs/react';
import { Check, TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import AiConnectionController from '@/actions/App/Http/Controllers/Settings/AiConnectionController';
import { useConfirm } from '@/components/confirm-dialog';
import { CopyableUrl } from '@/components/copyable-url';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { formatThaiDate } from '@/lib/thai-date';
import { index } from '@/routes/ai-connections';

type Connection = {
    clientId: string;
    name: string;
    connectedOn: string;
};

type Props = {
    myConnections: Connection[];
    setup: { mcpUrl: string; isPubliclyReachable: boolean } | null;
    members:
        { userId: number; name: string; connections: Connection[] }[] | null;
};

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

export default function AiConnections({
    myConnections,
    setup,
    members,
}: Props) {
    const confirm = useConfirm();
    const { auth } = usePage().props;

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
                AiConnectionController.destroy.url({
                    user: userId,
                    client: connection.clientId,
                }),
                { preserveScroll: true },
            );
        }
    };

    const isConnected = myConnections.length > 0;

    return (
        <>
            <Head title="เชื่อมต่อ Claude" />

            <div className="grid grid-cols-1 gap-12 lg:grid-cols-2 lg:gap-x-16">
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
                                        ต้องเปิดแอปบนโดเมนที่ขึ้นต้นด้วย https
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
                                    ด้วยบัญชี Claude ของคนนั้น แล้วไปที่ ตั้งค่า
                                    →{' '}
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
                                    Claude จะพามาหน้าเข้าสู่ระบบของแอปนี้{' '}
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
                                        member.connections.map((connection) => (
                                            <li
                                                key={`${member.userId}-${connection.clientId}`}
                                                className="flex min-h-12 items-center gap-3 px-4 py-3"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-lg font-medium md:text-base">
                                                        {member.name}{' '}
                                                        <span className="font-normal text-muted-foreground">
                                                            · {connection.name}
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
                                        ))
                                    ),
                                )}
                            </ul>
                        </Section>
                    )}
                </div>
            </div>
        </>
    );
}

AiConnections.layout = {
    breadcrumbs: [{ title: 'เชื่อมต่อ Claude', href: index() }],
};
