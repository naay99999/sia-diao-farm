import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import OrchardController from '@/actions/App/Http/Controllers/Farm/OrchardController';
import TreeController from '@/actions/App/Http/Controllers/Farm/TreeController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import OrchardFormFields from '@/components/orchard-form-fields';
import TreeFormFields from '@/components/tree-form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/orchards';
import { show as showTree } from '@/routes/trees';
import type { Orchard, Tree } from '@/types/farm';

export default function OrchardShow({
    orchard,
    trees,
    showRemoved,
}: {
    orchard: Orchard;
    trees: Tree[];
    showRemoved: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const [addingOne, setAddingOne] = useState(false);
    const [addingMany, setAddingMany] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: 'สวน', href: index() },
            { title: orchard.name, href: show(orchard.id) },
        ],
    });

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title={orchard.name} />
            <div className="flex items-center justify-between gap-2">
                <Heading
                    title={orchard.name}
                    description={orchard.location ?? undefined}
                />
                <div className="flex gap-2">
                    <Dialog open={editing} onOpenChange={setEditing}>
                        <DialogTrigger asChild>
                            <Button variant="outline">แก้ไข</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>แก้ไขสวน</DialogTitle>
                                <DialogDescription>
                                    แก้ไขข้อมูลสวน
                                </DialogDescription>
                            </DialogHeader>
                            <Form
                                {...OrchardController.update.form(orchard.id)}
                                onSuccess={() => setEditing(false)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <OrchardFormFields
                                            orchard={orchard}
                                            errors={errors}
                                        />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            บันทึก
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                    <Button
                        variant="destructive"
                        onClick={() => {
                            if (confirm('ลบสวนนี้และข้อมูลทั้งหมดในสวน?')) {
                                router.delete(
                                    OrchardController.destroy.url(orchard.id),
                                );
                            }
                        }}
                    >
                        ลบ
                    </Button>
                </div>
            </div>

            {orchard.notes && <p className="text-sm">{orchard.notes}</p>}

            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-medium">ต้นไม้ ({trees.length})</h2>
                <div className="flex flex-wrap gap-2">
                    <Dialog open={addingOne} onOpenChange={setAddingOne}>
                        <DialogTrigger asChild>
                            <Button>เพิ่มต้นไม้</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>เพิ่มต้นไม้</DialogTitle>
                                <DialogDescription>
                                    เพิ่มต้นไม้ทีละต้น
                                </DialogDescription>
                            </DialogHeader>
                            <Form
                                {...TreeController.store.form(orchard.id)}
                                onSuccess={() => setAddingOne(false)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <TreeFormFields errors={errors} />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            บันทึก
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                    <Dialog open={addingMany} onOpenChange={setAddingMany}>
                        <DialogTrigger asChild>
                            <Button variant="outline">เพิ่มหลายต้น</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>เพิ่มหลายต้น</DialogTitle>
                                <DialogDescription>
                                    สร้างรหัสต้นไม้ต่อเนื่อง เช่น A-001, A-002
                                </DialogDescription>
                            </DialogHeader>
                            <Form
                                {...TreeController.storeBatch.form(orchard.id)}
                                onSuccess={() => setAddingMany(false)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="batch-prefix">
                                                คำนำหน้ารหัส
                                            </Label>
                                            <Input
                                                id="batch-prefix"
                                                name="prefix"
                                                required
                                                placeholder="เช่น A-"
                                            />
                                            <InputError
                                                message={errors.prefix}
                                            />
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="grid gap-2">
                                                <Label htmlFor="batch-start">
                                                    เริ่มที่เลข
                                                </Label>
                                                <Input
                                                    id="batch-start"
                                                    name="start"
                                                    type="number"
                                                    min={1}
                                                    defaultValue={1}
                                                    required
                                                />
                                                <InputError
                                                    message={errors.start}
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="batch-count">
                                                    จำนวน (ต้น)
                                                </Label>
                                                <Input
                                                    id="batch-count"
                                                    name="count"
                                                    type="number"
                                                    min={1}
                                                    max={500}
                                                    required
                                                />
                                                <InputError
                                                    message={errors.count}
                                                />
                                            </div>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="batch-variety">
                                                พันธุ์
                                            </Label>
                                            <Input
                                                id="batch-variety"
                                                name="variety"
                                                placeholder="เช่น หมอนทอง"
                                            />
                                            <InputError
                                                message={errors.variety}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            สร้างต้นไม้
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                    <Button
                        variant="ghost"
                        onClick={() =>
                            router.get(
                                show.url(orchard.id),
                                showRemoved ? {} : { show_removed: 1 },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {showRemoved
                            ? 'ซ่อนต้นที่ไม่อยู่แล้ว'
                            : 'แสดงต้นที่ไม่อยู่แล้ว'}
                    </Button>
                </div>
            </div>

            {trees.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    ยังไม่มีต้นไม้ — กดเพิ่มต้นไม้เพื่อเริ่มต้น
                </p>
            )}
            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                {trees.map((tree) => (
                    <Link
                        key={tree.id}
                        href={showTree(tree.id)}
                        className="flex items-center justify-between gap-2 rounded-lg border p-3 transition hover:border-foreground/20"
                    >
                        <div>
                            <p className="font-medium">{tree.code}</p>
                            {tree.variety && (
                                <p className="text-sm text-muted-foreground">
                                    {tree.variety}
                                </p>
                            )}
                        </div>
                        {tree.removed_on && (
                            <Badge variant="secondary">ไม่อยู่แล้ว</Badge>
                        )}
                    </Link>
                ))}
            </div>
        </div>
    );
}
