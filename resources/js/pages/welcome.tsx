import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login } from '@/routes';

export default function Welcome() {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="ยินดีต้อนรับ" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-background p-6">
                <main className="flex flex-col items-center gap-6 text-center">
                    <h1 className="text-2xl font-semibold">{name}</h1>
                    <p className="text-muted-foreground">ยินดีต้อนรับ</p>
                    <nav className="flex items-center gap-4">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-block rounded-sm border px-5 py-1.5 text-sm leading-normal hover:border-foreground/20"
                            >
                                แดชบอร์ด
                            </Link>
                        ) : (
                            <Link
                                href={login()}
                                className="inline-block rounded-sm border px-5 py-1.5 text-sm leading-normal hover:border-foreground/20"
                            >
                                เข้าสู่ระบบ
                            </Link>
                        )}
                    </nav>
                </main>
            </div>
        </>
    );
}
