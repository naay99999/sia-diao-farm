import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

/**
 * The one save action of a form: full width on a phone, with a spinner while the request runs.
 */
export function SubmitButton({
    processing,
    children = 'บันทึก',
    className,
}: {
    processing: boolean;
    children?: ReactNode;
    className?: string;
}) {
    return (
        <Button
            type="submit"
            disabled={processing}
            className={cn('w-full', className)}
        >
            {processing && <Spinner />}
            {children}
        </Button>
    );
}
