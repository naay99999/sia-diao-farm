import { Head } from '@inertiajs/react';

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

export default function Assistant(props: Props) {
    void props;

    return (
        <>
            <Head title="ผู้ช่วย AI" />
            <h1 className="px-4 py-6 text-2xl font-semibold">ผู้ช่วย AI</h1>
        </>
    );
}
