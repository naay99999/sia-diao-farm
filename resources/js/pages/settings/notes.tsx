import { Form, Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import FarmNoteController from '@/actions/App/Http/Controllers/Settings/FarmNoteController';
import { useConfirm } from '@/components/confirm-dialog';
import { AiBadge } from '@/components/farm/ai-badge';
import { EmptyState } from '@/components/farm/empty-state';
import { RowActions } from '@/components/farm/row-actions';
import { SubmitButton } from '@/components/farm/submit-button';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatThaiDate } from '@/lib/thai-date';
import { index } from '@/routes/notes';

type Note = {
    id: number;
    body: string;
    findingId: number | null;
    findingName: string | null;
    treeId: number | null;
    treeCode: string | null;
    orchardName: string | null;
    createdBy: string | null;
    createdAt: string | null;
    fromAi: boolean;
    aiBy: string | null;
};

type FindingOption = { id: number; name: string };

type TreeOption = { id: number; code: string; orchardName: string };

type Props = {
    notes: Note[];
    findings: FindingOption[];
    trees: TreeOption[];
};

const selectClass =
    'flex h-12 w-full rounded-md border border-input bg-transparent px-3 py-1 text-lg shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-60 md:h-9 md:text-base';

function NoteFields({
    note,
    findings,
    trees,
    errors,
}: {
    note?: Note;
    findings: FindingOption[];
    trees: TreeOption[];
    errors: Partial<Record<'body' | 'finding_id' | 'tree_id', string>>;
}) {
    const orchards = [...new Set(trees.map((tree) => tree.orchardName))];

    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="note-body">ข้อความ</Label>
                <Textarea
                    id="note-body"
                    name="body"
                    required
                    maxLength={5000}
                    rows={4}
                    placeholder="เช่น ช่วงดอกบานห้ามฉีดยาฆ่าแมลง"
                    defaultValue={note?.body}
                />
                <InputError message={errors.body} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="note-finding">เกี่ยวกับสิ่งที่พบ</Label>
                <select
                    id="note-finding"
                    name="finding_id"
                    defaultValue={note?.findingId ?? ''}
                    className={selectClass}
                >
                    <option value="">ไม่ระบุ</option>
                    {findings.map((finding) => (
                        <option key={finding.id} value={finding.id}>
                            {finding.name}
                        </option>
                    ))}
                </select>
                <InputError message={errors.finding_id} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="note-tree">เกี่ยวกับต้น</Label>
                <select
                    id="note-tree"
                    name="tree_id"
                    defaultValue={note?.treeId ?? ''}
                    className={selectClass}
                >
                    <option value="">ไม่ระบุ (ใช้ได้ทุกต้น)</option>
                    {orchards.map((orchard) => (
                        <optgroup key={orchard} label={orchard}>
                            {trees
                                .filter((tree) => tree.orchardName === orchard)
                                .map((tree) => (
                                    <option key={tree.id} value={tree.id}>
                                        {tree.code}
                                    </option>
                                ))}
                        </optgroup>
                    ))}
                </select>
                <InputError message={errors.tree_id} />
            </div>
        </>
    );
}

export default function Notes({ notes, findings, trees }: Props) {
    const confirm = useConfirm();
    const [isAdding, setIsAdding] = useState(false);
    const [editing, setEditing] = useState<Note | null>(null);

    const remove = async (note: Note) => {
        const confirmed = await confirm({
            title: 'ลบโน้ตนี้?',
            description: 'กู้คืนได้จากถังขยะในหน้าบันทึก',
            confirmLabel: 'ลบ',
            destructive: true,
        });

        if (confirmed) {
            router.delete(FarmNoteController.destroy.url(note.id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head title="ความรู้ที่จดไว้" />

            <div className="max-w-2xl space-y-6">
                <div className="flex items-start justify-between gap-3">
                    <Heading
                        variant="small"
                        title="ความรู้ที่จดไว้"
                        description="สิ่งที่สวนอยากจำไว้ Claude ใช้โน้ตเหล่านี้ตอนให้คำแนะนำ"
                    />
                    <Button
                        type="button"
                        className="shrink-0"
                        onClick={() => setIsAdding(true)}
                    >
                        <Plus />
                        เพิ่มโน้ต
                    </Button>
                </div>

                {notes.length === 0 ? (
                    <EmptyState>
                        ยังไม่มีโน้ต จดผ่าน Claude ได้ เช่น “จำไว้ว่า
                        ช่วงดอกบานห้ามฉีดยาฆ่าแมลง”
                    </EmptyState>
                ) : (
                    <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                        {notes.map((note) => (
                            <li key={note.id} className="flex gap-3 px-4 py-3">
                                <div className="min-w-0 flex-1 space-y-1.5">
                                    <p className="text-lg break-words whitespace-pre-line md:text-base">
                                        {note.body}
                                    </p>
                                    <div className="flex flex-wrap items-center gap-2 text-base text-muted-foreground md:text-sm">
                                        {note.findingName && (
                                            <Badge variant="secondary">
                                                {note.findingName}
                                            </Badge>
                                        )}
                                        {note.treeCode && (
                                            <Badge variant="secondary">
                                                {note.orchardName} ·{' '}
                                                {note.treeCode}
                                            </Badge>
                                        )}
                                        {note.createdAt && (
                                            <span>
                                                {formatThaiDate(note.createdAt)}
                                            </span>
                                        )}
                                        {note.createdBy && (
                                            <span>{note.createdBy}</span>
                                        )}
                                        {note.fromAi && (
                                            <AiBadge by={note.aiBy} />
                                        )}
                                    </div>
                                </div>
                                <RowActions
                                    label="โน้ต"
                                    onEdit={() => setEditing(note)}
                                    onDelete={() => remove(note)}
                                />
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <Dialog open={isAdding} onOpenChange={setIsAdding}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>เพิ่มโน้ต</DialogTitle>
                        <DialogDescription>
                            ผูกกับสิ่งที่พบหรือต้นได้ ถ้าไม่ระบุจะเป็นโน้ตทั่วไป
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        {...FarmNoteController.store.form()}
                        onSuccess={() => setIsAdding(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <NoteFields
                                    findings={findings}
                                    trees={trees}
                                    errors={errors}
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
                        <DialogTitle>แก้ไขโน้ต</DialogTitle>
                        <DialogDescription>
                            ผูกกับสิ่งที่พบหรือต้นได้ ถ้าไม่ระบุจะเป็นโน้ตทั่วไป
                        </DialogDescription>
                    </DialogHeader>
                    {editing && (
                        <Form
                            {...FarmNoteController.update.form(editing.id)}
                            onSuccess={() => setEditing(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <NoteFields
                                        note={editing}
                                        findings={findings}
                                        trees={trees}
                                        errors={errors}
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

Notes.layout = {
    breadcrumbs: [
        {
            title: 'ความรู้ที่จดไว้',
            href: index(),
        },
    ],
};
