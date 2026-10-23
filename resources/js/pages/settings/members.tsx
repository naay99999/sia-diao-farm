import { Form, Head, router } from '@inertiajs/react';
import { MoreVertical, Plus } from 'lucide-react';
import { useState } from 'react';
import MemberController from '@/actions/App/Http/Controllers/Settings/MemberController';
import { useConfirm } from '@/components/confirm-dialog';
import { SubmitButton } from '@/components/farm/submit-button';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { index } from '@/routes/members';

type Member = {
    id: number;
    name: string;
    email: string;
    isOwner: boolean;
    isActive: boolean;
    isConnected: boolean;
};

function PasswordField({
    passwordRules,
    error,
    label,
}: {
    passwordRules: string;
    error?: string;
    label: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor="password">{label}</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                autoComplete="new-password"
                passwordrules={passwordRules}
            />
            <p className="text-base text-muted-foreground md:text-sm">
                บอกรหัสนี้กับเขาเอง เขาเปลี่ยนเองได้ที่ ตั้งค่า → โปรไฟล์
            </p>
            <InputError message={error} />
        </div>
    );
}

export default function Members({
    members,
    passwordRules,
}: {
    members: Member[];
    passwordRules: string;
}) {
    const confirm = useConfirm();
    const [isAdding, setIsAdding] = useState(false);
    const [editing, setEditing] = useState<Member | null>(null);
    const [resetting, setResetting] = useState<Member | null>(null);

    const deactivate = async (member: Member) => {
        const confirmed = await confirm({
            title: `ปิดใช้งานบัญชี ${member.name}?`,
            description: `${member.name} จะล็อกอินไม่ได้ และ Claude ที่เชื่อมด้วยบัญชีนี้จะใช้ไม่ได้ทันที เปิดใช้งานกลับมาได้ภายหลัง`,
            confirmLabel: 'ปิดใช้งาน',
            destructive: true,
        });

        if (confirmed) {
            router.patch(
                MemberController.deactivate.url(member.id),
                {},
                { preserveScroll: true },
            );
        }
    };

    const activate = (member: Member) => {
        router.patch(
            MemberController.activate.url(member.id),
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="สมาชิก" />

            <div className="max-w-2xl space-y-6">
                <div className="flex items-start justify-between gap-3">
                    <Heading
                        variant="small"
                        title="สมาชิก"
                        description="เพิ่มคนในครอบครัวเพื่อให้ใช้แอปและเชื่อม Claude ด้วยบัญชีของตัวเอง"
                    />
                    <Button
                        type="button"
                        className="shrink-0"
                        onClick={() => setIsAdding(true)}
                    >
                        <Plus />
                        เพิ่มสมาชิก
                    </Button>
                </div>

                <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                    {members.map((member) => (
                        <li
                            key={member.id}
                            className="flex min-h-12 items-center gap-3 px-4 py-3"
                        >
                            <div
                                className={cn(
                                    'min-w-0 flex-1',
                                    !member.isActive && 'opacity-60',
                                )}
                            >
                                <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-lg font-medium md:text-base">
                                    <span className="truncate">
                                        {member.name}
                                    </span>
                                    {member.isOwner && (
                                        <Badge variant="outline">เจ้าของ</Badge>
                                    )}
                                    {!member.isActive && (
                                        <Badge variant="secondary">
                                            ปิดใช้งาน
                                        </Badge>
                                    )}
                                </p>
                                <p className="truncate text-base text-muted-foreground md:text-sm">
                                    {member.email} ·{' '}
                                    {member.isConnected
                                        ? 'Claude เชื่อมอยู่'
                                        : 'ยังไม่เชื่อม'}
                                </p>
                            </div>

                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="-mr-2 shrink-0 text-muted-foreground"
                                        aria-label={`ตัวเลือกสำหรับ ${member.name}`}
                                    >
                                        <MoreVertical />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    className="min-w-48"
                                >
                                    <DropdownMenuItem
                                        onSelect={() => setEditing(member)}
                                    >
                                        แก้ชื่อ/อีเมล
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onSelect={() => setResetting(member)}
                                    >
                                        ตั้งรหัสผ่านใหม่
                                    </DropdownMenuItem>
                                    {!member.isOwner && (
                                        <>
                                            <DropdownMenuSeparator />
                                            {member.isActive ? (
                                                <DropdownMenuItem
                                                    variant="destructive"
                                                    onSelect={() =>
                                                        deactivate(member)
                                                    }
                                                >
                                                    ปิดใช้งาน
                                                </DropdownMenuItem>
                                            ) : (
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        activate(member)
                                                    }
                                                >
                                                    เปิดใช้งาน
                                                </DropdownMenuItem>
                                            )}
                                        </>
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </li>
                    ))}
                </ul>
            </div>

            <Dialog open={isAdding} onOpenChange={setIsAdding}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>เพิ่มสมาชิก</DialogTitle>
                        <DialogDescription>
                            สร้างบัญชีให้คนในครอบครัวล็อกอินด้วยอีเมลและรหัสผ่านนี้
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        {...MemberController.store.form()}
                        onSuccess={() => setIsAdding(false)}
                        disableWhileProcessing
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">ชื่อ</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        placeholder="เช่น แม่"
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">
                                        อีเมล (ใช้ล็อกอิน)
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        placeholder="mae@example.com"
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.email} />
                                </div>
                                <PasswordField
                                    label="รหัสผ่านเริ่มต้น"
                                    passwordRules={passwordRules}
                                    error={errors.password}
                                />
                                <SubmitButton processing={processing}>
                                    เพิ่ม
                                </SubmitButton>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>แก้ชื่อ/อีเมล</DialogTitle>
                        <DialogDescription>{editing?.name}</DialogDescription>
                    </DialogHeader>
                    {editing && (
                        <Form
                            {...MemberController.update.form(editing.id)}
                            onSuccess={() => setEditing(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-name">ชื่อ</Label>
                                        <Input
                                            id="edit-name"
                                            name="name"
                                            required
                                            defaultValue={editing.name}
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-email">
                                            อีเมล (ใช้ล็อกอิน)
                                        </Label>
                                        <Input
                                            id="edit-email"
                                            type="email"
                                            name="email"
                                            required
                                            defaultValue={editing.email}
                                        />
                                        <InputError message={errors.email} />
                                    </div>
                                    <SubmitButton processing={processing} />
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={resetting !== null}
                onOpenChange={(open) => !open && setResetting(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>ตั้งรหัสผ่านใหม่</DialogTitle>
                        <DialogDescription>{resetting?.name}</DialogDescription>
                    </DialogHeader>
                    {resetting && (
                        <Form
                            {...MemberController.resetPassword.form(
                                resetting.id,
                            )}
                            onSuccess={() => setResetting(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <PasswordField
                                        label="รหัสผ่านใหม่"
                                        passwordRules={passwordRules}
                                        error={errors.password}
                                    />
                                    <SubmitButton processing={processing} />
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

Members.layout = {
    breadcrumbs: [
        {
            title: 'สมาชิก',
            href: index(),
        },
    ],
};
