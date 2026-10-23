import { Check, Copy } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export function CopyableUrl({ url }: { url: string }) {
    const input = useRef<HTMLInputElement>(null);
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!copied) {
            return;
        }

        const timer = window.setTimeout(() => setCopied(false), 2000);

        return () => window.clearTimeout(timer);
    }, [copied]);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(url);
            setCopied(true);
        } catch {
            // No clipboard access (plain http on a phone): select the text so it can be copied by hand.
            input.current?.select();
        }
    };

    return (
        <div className="flex flex-col gap-2 sm:flex-row">
            <label htmlFor="mcp-url" className="sr-only">
                ที่อยู่สำหรับเชื่อมต่อ
            </label>
            <Input
                id="mcp-url"
                ref={input}
                readOnly
                value={url}
                onFocus={(event) => event.currentTarget.select()}
                className="min-w-0 flex-1 bg-muted/40"
            />
            <Button type="button" onClick={copy} className="shrink-0">
                {copied ? <Check /> : <Copy />}
                {copied ? 'คัดลอกแล้ว' : 'คัดลอก'}
            </Button>
        </div>
    );
}
