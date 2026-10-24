import { Form, Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import FindingController from '@/actions/App/Http/Controllers/Settings/FindingController';
import { useConfirm } from '@/components/confirm-dialog';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/findings';

type FindingKind = 'problem' | 'stage';

type Finding = {
    id: number;
    name: string;
    kind: FindingKind;
    inUse: boolean;
};

const kinds: { value: FindingKind; label: string }[] = [
    { value: 'problem', label: 'ปัญหา' },
    { value: 'stage', label: 'ระยะการเติบโต' },
];

const selectClass =
    'flex h-12 w-full rounded-md border border-input bg-transparent px-3 py-1 text-lg shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-60 md:h-9 md:text-base';

function FindingFields({
    finding,
    errors,
}: {
    finding?: Finding;
    errors: Partial<Record<'name' | 'kind', string>>;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="finding-name">ชื่อ</Label>
                <Input
                    id="finding-name"
                    name="name"
                    required
                    maxLength={100}
                    placeholder="เช่น ไรแดง"
                    autoComplete="off"
                    defaultValue={finding?.name}
                />
                <InputError message={errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="finding-kind">ชนิด</Label>
                {finding?.inUse && (
                    <input type="hidden" name="kind" value={finding.kind} />
                )}
                <select
                    id="finding-kind"
                    name={finding?.inUse ? undefined : 'kind'}
                    defaultValue={finding?.kind ?? 'problem'}
                    disabled={finding?.inUse}
                    className={selectClass}
                >
                    {kinds.map((kind) => (
                        <option key={kind.value} value={kind.value}>
                            {kind.label}
                        </option>
                    ))}
                </select>
                {finding?.inUse && (
                    <p className="text-base text-muted-foreground md:text-sm">
                        เปลี่ยนชนิดไม่ได้ เพราะมีบันทึกใช้อยู่
                    </p>
                )}
                <InputError message={errors.kind} />
            </div>
        </>
    );
}

export default function Findings({ findings }: { findings: Finding[] }) {
    const confirm = useConfirm();
    const [isAdding, setIsAdding] = useState(false);
    const [editing, setEditing] = useState<Finding | null>(null);

    const remove = async (finding: Finding) => {
        const confirmed = await confirm({
            title: `ลบ ${finding.name}?`,
            description: 'ลบได้เฉพาะรายการที่ยังไม่มีบันทึกใช้อยู่',
            confirmLabel: 'ลบ',
            destructive: true,
        });

        if (confirmed) {
            router.delete(FindingController.destroy.url(finding.id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head title="สิ่งที่พบ" />

            <div className="max-w-2xl space-y-6">
                <div className="flex items-start justify-between gap-3">
                    <Heading
                        variant="small"
                        title="สิ่งที่พบ"
                        description="รายการปัญหาและระยะการเติบโตที่ใช้จดบันทึกต้นไม้"
                    />
                    <Button
                        type="button"
                        className="shrink-0"
                        onClick={() => setIsAdding(true)}
                    >
                        <Plus />
                        เพิ่มรายการ
                    </Button>
                </div>

                {kinds.map((kind) => {
                    const items = findings.filter(
                        (finding) => finding.kind === kind.value,
                    );

                    return (
                        <section key={kind.value} className="space-y-2">
                            <h3 className="text-lg font-medium md:text-base">
                                {kind.label}
                            </h3>
                            {items.length === 0 ? (
                                <EmptyState>ยังไม่มีรายการ</EmptyState>
                            ) : (
                                <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                                    {items.map((finding) => (
                                        <li
                                            key={finding.id}
                                            className="flex min-h-12 items-center gap-3 px-4 py-2"
                                        >
                                            <p className="min-w-0 flex-1 truncate text-lg md:text-base">
                                                {finding.name}
                                            </p>
                                            {finding.inUse && (
                                                <Badge variant="secondary">
                                                    มีบันทึกใช้อยู่
                                                </Badge>
                                            )}
                                            <RowActions
                                                label={finding.name}
                                                onEdit={() =>
                                                    setEditing(finding)
                                                }
                                                onDelete={() =>
                                                    remove(finding)
                                                }
                                            />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    );
                })}
            </div>

            <Dialog open={isAdding} onOpenChange={setIsAdding}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>เพิ่มรายการ</DialogTitle>
                        <DialogDescription>
                            ชื่อต้องไม่ซ้ำกับรายการที่มีอยู่
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        {...FindingController.store.form()}
                        onSuccess={() => setIsAdding(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <FindingFields errors={errors} />
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
                        <DialogTitle>แก้ไขรายการ</DialogTitle>
                        <DialogDescription>{editing?.name}</DialogDescription>
                    </DialogHeader>
                    {editing && (
                        <Form
                            {...FindingController.update.form(editing.id)}
                            onSuccess={() => setEditing(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <FindingFields
                                        finding={editing}
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

Findings.layout = {
    breadcrumbs: [
        {
            title: 'สิ่งที่พบ',
            href: index(),
        },
    ],
};
