import {
    createContext,
    useCallback,
    useContext,
    useRef,
    useState,
} from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ConfirmOptions = {
    title: string;
    description?: string;
    confirmLabel?: string;
    destructive?: boolean;
};

type Confirm = (options: ConfirmOptions) => Promise<boolean>;

const ConfirmContext = createContext<Confirm | null>(null);

/**
 * Hosts one confirmation dialog for the whole app, replacing window.confirm().
 */
export function ConfirmProvider({ children }: { children: ReactNode }) {
    const [options, setOptions] = useState<ConfirmOptions | null>(null);
    const resolver = useRef<((confirmed: boolean) => void) | null>(null);

    const confirm = useCallback<Confirm>((next) => {
        setOptions(next);

        return new Promise<boolean>((resolve) => {
            resolver.current = resolve;
        });
    }, []);

    const settle = (confirmed: boolean) => {
        resolver.current?.(confirmed);
        resolver.current = null;
        setOptions(null);
    };

    return (
        <ConfirmContext.Provider value={confirm}>
            {children}
            <Dialog
                open={options !== null}
                onOpenChange={(open) => !open && settle(false)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{options?.title}</DialogTitle>
                        {options?.description && (
                            <DialogDescription>
                                {options.description}
                            </DialogDescription>
                        )}
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => settle(false)}>
                            ยกเลิก
                        </Button>
                        <Button
                            variant={
                                options?.destructive ? 'destructive' : 'default'
                            }
                            onClick={() => settle(true)}
                        >
                            {options?.confirmLabel ?? 'ยืนยัน'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </ConfirmContext.Provider>
    );
}

export function useConfirm(): Confirm {
    const confirm = useContext(ConfirmContext);

    if (confirm === null) {
        throw new Error('useConfirm must be used inside ConfirmProvider');
    }

    return confirm;
}
