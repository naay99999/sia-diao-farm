import { router } from '@inertiajs/react';
import { Passkeys } from '@laravel/passkeys';
import { useEffect, useState } from 'react';
import { AddPasskeyButton } from '@/components/passkey-list';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export function PasskeyPrompt() {
    const [open, setOpen] = useState(false);

    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;

            if (flash?.suggestPasskey === true && Passkeys.isSupported()) {
                setOpen(true);
            }
        });
    }, []);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>เข้าสู่ระบบครั้งหน้าง่ายขึ้น</DialogTitle>
                    <DialogDescription>
                        เพิ่ม passkey
                        เพื่อเข้าสู่ระบบครั้งหน้าด้วยการสแกนนิ้วหรือใบหน้า
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="ghost" onClick={() => setOpen(false)}>
                        ไว้ทีหลัง
                    </Button>
                    <AddPasskeyButton
                        label="เพิ่มเลย"
                        onAdded={() => setOpen(false)}
                    />
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
