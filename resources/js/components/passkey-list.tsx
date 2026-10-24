import { router } from '@inertiajs/react';
import { UserCancelledError } from '@laravel/passkeys';
import { usePasskeyRegister } from '@laravel/passkeys/react';
import { Fingerprint, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { useConfirm } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { deviceName } from '@/lib/device-name';
import { formatThaiDate } from '@/lib/thai-date';
import { destroy, registrationOptions, store } from '@/routes/passkey';

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    createdAt: string;
    lastUsedAt: string | null;
};

type AddPasskeyButtonProps = {
    label?: string;
    onAdded?: () => void;
};

export function AddPasskeyButton({
    label = 'เพิ่ม passkey',
    onAdded,
}: AddPasskeyButtonProps) {
    const { register, isLoading, isSupported } = usePasskeyRegister({
        routes: {
            options: registrationOptions.url(),
            submit: store.url(),
        },
        onSuccess: () => {
            toast.success('เพิ่ม passkey แล้ว');
            router.reload({ only: ['passkeys'] });
            onAdded?.();
        },
        onError: (error) => {
            if (!(error instanceof UserCancelledError)) {
                toast.error('เพิ่ม passkey ไม่สำเร็จ');
            }
        },
    });

    if (!isSupported) {
        return null;
    }

    return (
        <Button
            type="button"
            disabled={isLoading}
            onClick={() => register(deviceName(navigator.userAgent))}
            data-test="add-passkey-button"
        >
            {isLoading ? <Spinner /> : <Fingerprint />}
            {label}
        </Button>
    );
}

export function PasskeyList({ passkeys }: { passkeys: Passkey[] }) {
    const confirm = useConfirm();

    const remove = async (passkey: Passkey) => {
        const confirmed = await confirm({
            title: `ลบ passkey "${passkey.name}"?`,
            description:
                'อุปกรณ์นี้จะใช้สแกนนิ้วหรือใบหน้าเข้าสู่ระบบไม่ได้อีก แต่ยังใช้รหัสผ่านได้ตามปกติ',
            confirmLabel: 'ลบ',
            destructive: true,
        });

        if (!confirmed) {
            return;
        }

        router.delete(destroy.url(passkey.id), {
            preserveScroll: true,
            onSuccess: () => toast.success('ลบ passkey แล้ว'),
        });
    };

    if (passkeys.length === 0) {
        return (
            <p className="text-base text-muted-foreground md:text-sm">
                ยังไม่มี passkey
                เพิ่มไว้เพื่อเข้าสู่ระบบด้วยการสแกนนิ้วหรือใบหน้า
            </p>
        );
    }

    return (
        <ul className="divide-y overflow-hidden rounded-lg border bg-card">
            {passkeys.map((passkey) => (
                <li
                    key={passkey.id}
                    className="flex min-h-12 items-center gap-3 px-4 py-3"
                >
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-lg font-medium md:text-base">
                            {passkey.name}
                            {passkey.authenticator && (
                                <span className="font-normal text-muted-foreground">
                                    {' '}
                                    · {passkey.authenticator}
                                </span>
                            )}
                        </p>
                        <p className="text-base text-muted-foreground md:text-sm">
                            เพิ่มเมื่อ {formatThaiDate(passkey.createdAt)} ·{' '}
                            {passkey.lastUsedAt
                                ? `ใช้ล่าสุด ${formatThaiDate(passkey.lastUsedAt)}`
                                : 'ยังไม่เคยใช้'}
                        </p>
                    </div>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="-mr-2 shrink-0 text-muted-foreground"
                        aria-label={`ลบ passkey ${passkey.name}`}
                        onClick={() => remove(passkey)}
                    >
                        <Trash2 />
                    </Button>
                </li>
            ))}
        </ul>
    );
}
