import { MoreVertical } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

/**
 * One kebab per row: edit first, the destructive action last and in red, so a delete is never one stray tap away.
 */
export function RowActions({
    label,
    onEdit,
    onDelete,
    deleteLabel = 'ลบ',
}: {
    label: string;
    onEdit: () => void;
    onDelete: () => void;
    deleteLabel?: string;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="-mr-2 shrink-0 text-muted-foreground"
                    aria-label={`ตัวเลือกสำหรับ ${label}`}
                >
                    <MoreVertical />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-40">
                <DropdownMenuItem onSelect={onEdit}>แก้ไข</DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem variant="destructive" onSelect={onDelete}>
                    {deleteLabel}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
