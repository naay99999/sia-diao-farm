import { Check, Copy } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

const promptGroups: { title: string; prompts: string[] }[] = [
    {
        title: 'ถามข้อมูล',
        prompts: [
            'วันนี้ต้นไหนต้องดูซ้ำบ้าง',
            'ต้น A-012 ปีนี้เก็บได้กี่กิโล',
            'เดือนนี้จ่ายค่าปุ๋ยไปเท่าไหร่',
        ],
    },
    {
        title: 'บันทึก',
        prompts: [
            'วันนี้ใส่ปุ๋ย 15-15-15 ต้น A-001 ถึง A-010 ต้นละ 1 กิโล',
            'ต้น A-005 ใบไหม้ ไม่หนัก',
            'ขายทุเรียนให้เจ๊หมวย 300 กิโล ได้ 45,000 บาท',
        ],
    },
    {
        title: 'ดูซ้ำ',
        prompts: [
            'ต้น A-005 ที่ใบไหม้ ไปดูแล้วหายแล้ว',
            'ต้น B-003 ยังไม่หาย นัดดูอีกทีอาทิตย์หน้า',
        ],
    },
    {
        title: 'ขอคำแนะนำ',
        prompts: ['ต้นที่เป็นราสีชมพู เคยใช้ยาอะไรแล้วหายเร็วที่สุด'],
    },
    {
        title: 'จดความรู้',
        prompts: ['จำไว้ว่า ช่วงดอกบานห้ามฉีดยาฆ่าแมลง'],
    },
    {
        title: 'แก้ ลบ กู้คืน',
        prompts: [
            'เมื่อกี้บันทึกผิด แก้เป็น 25 กิโล',
            'ลบบันทึกขายเมื่อวานออก',
            'เอาบันทึกที่ลบไปเมื่อวานกลับมา',
        ],
    },
];

function PromptRow({ prompt }: { prompt: string }) {
    const text = useRef<HTMLSpanElement>(null);
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
            await navigator.clipboard.writeText(prompt);
            setCopied(true);
        } catch {
            // No clipboard access (plain http on a phone): select the sentence so it can be copied by hand.
            if (text.current) {
                const range = document.createRange();
                range.selectNodeContents(text.current);
                const selection = window.getSelection();
                selection?.removeAllRanges();
                selection?.addRange(range);
            }
        }
    };

    return (
        <li>
            <button
                type="button"
                onClick={copy}
                aria-label={`คัดลอกประโยค ${prompt}`}
                className="flex min-h-12 w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-accent/60 focus-visible:bg-accent/60 focus-visible:outline-none active:bg-accent"
            >
                <span
                    ref={text}
                    className="min-w-0 flex-1 text-lg md:text-base"
                >
                    “{prompt}”
                </span>
                <span
                    className={cn(
                        'flex shrink-0 items-center gap-1.5 text-base text-muted-foreground md:text-sm',
                        copied && 'font-medium text-primary',
                    )}
                    aria-live="polite"
                >
                    {copied ? (
                        <Check className="size-4" aria-hidden />
                    ) : (
                        <Copy className="size-4" aria-hidden />
                    )}
                    {copied ? 'คัดลอกแล้ว' : 'คัดลอก'}
                </span>
            </button>
        </li>
    );
}

/**
 * Sentences a family member can copy and paste into Claude, grouped by what they want to do.
 */
export function ExamplePrompts() {
    return (
        <div className="space-y-6">
            {promptGroups.map((group) => (
                <div key={group.title} className="space-y-2">
                    <h3 className="text-lg font-medium md:text-base">
                        {group.title}
                    </h3>
                    <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                        {group.prompts.map((prompt) => (
                            <PromptRow key={prompt} prompt={prompt} />
                        ))}
                    </ul>
                </div>
            ))}
        </div>
    );
}
