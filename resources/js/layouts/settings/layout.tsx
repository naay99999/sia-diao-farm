import { Link, router, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { index as aiConnectionsIndex } from '@/routes/ai-connections';
import { index as membersIndex } from '@/routes/members';
import { edit as profileEdit } from '@/routes/profile';

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentUrl } = useCurrentUrl();
    const { auth } = usePage().props;
    const isOwner = auth.user.is_owner;

    const settingsNavItems = [
        { title: 'โปรไฟล์', href: profileEdit() },
        ...(isOwner ? [{ title: 'สมาชิก', href: membersIndex() }] : []),
        { title: 'เชื่อมต่อ Claude', href: aiConnectionsIndex() },
    ];

    return (
        <div className="space-y-6 px-4 py-6 md:px-6">
            <Heading
                title="ตั้งค่า"
                description={
                    isOwner ? 'บัญชีของคุณและสมาชิกในครอบครัว' : 'บัญชีของคุณ'
                }
            />

            <nav
                aria-label="หมวดการตั้งค่า"
                className="-mx-4 flex [scrollbar-width:none] overflow-x-auto border-b px-4 md:mx-0 md:px-0"
            >
                {settingsNavItems.map((item) => {
                    const isActive = isCurrentUrl(item.href);

                    return (
                        <Link
                            key={item.title}
                            href={item.href}
                            prefetch
                            aria-current={isActive ? 'page' : undefined}
                            className={cn(
                                '-mb-px flex h-12 shrink-0 items-center border-b-2 border-transparent px-2.5 text-lg font-medium whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none md:h-10 md:px-3 md:text-base',
                                isActive && 'border-primary text-foreground',
                            )}
                        >
                            {item.title}
                        </Link>
                    );
                })}
            </nav>

            {/* Each settings page sets its own width: forms stay narrow, overviews use the canvas. */}
            {children}

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
