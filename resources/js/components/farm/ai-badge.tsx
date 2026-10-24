import { Badge } from '@/components/ui/badge';

/**
 * Marks a record Claude wrote, with the member who was using Claude at the time.
 */
export function AiBadge({ by }: { by: string | null }) {
    return (
        <Badge variant="outline">
            <span className="sr-only">บันทึกโดย </span>
            AI
            {by && ` · ${by}`}
        </Badge>
    );
}
