import { Link, router } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

export default function SettingsLayout({ children }: PropsWithChildren) {
    return (
        <div className="space-y-8 px-4 py-6 md:px-6">
            <Heading
                title="ตั้งค่า"
                description="จัดการโปรไฟล์และการตั้งค่าบัญชีของคุณ"
            />

            <div className="flex-1 md:max-w-2xl">
                <section className="max-w-xl space-y-12">{children}</section>
            </div>

            <div className="max-w-xl border-t pt-6 md:hidden">
                <Button variant="outline" className="w-full" asChild>
                    <Link
                        href={logout()}
                        as="button"
                        onClick={() => router.flushAll()}
                        data-test="logout-button-mobile"
                    >
                        ออกจากระบบ
                    </Link>
                </Button>
            </div>
        </div>
    );
}
