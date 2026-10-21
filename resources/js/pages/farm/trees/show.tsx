import { Form, Head, router, setLayoutProps } from '@inertiajs/react';
import { MoreVertical } from 'lucide-react';
import { useState } from 'react';
import CareRecordController from '@/actions/App/Http/Controllers/Farm/CareRecordController';
import TreeController from '@/actions/App/Http/Controllers/Farm/TreeController';
import { CareRecordFields } from '@/components/farm/care-record-form';
import Heading from '@/components/heading';
import TreeFormFields from '@/components/tree-form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { index, show } from '@/routes/orchards';
import { show as showTree } from '@/routes/trees';
import type { CareType, Orchard, Tree } from '@/types/farm';

type TreeWithOrchard = Tree & { orchard: Orchard };

export default function TreeShow({ tree }: { tree: TreeWithOrchard }) {
    const [editing, setEditing] = useState(false);
    const [careType, setCareType] = useState<CareType | null>(null);
    const isRemoved = tree.removed_on !== null;

    setLayoutProps({
        breadcrumbs: [
            { title: 'สวน', href: index() },
            { title: tree.orchard.name, href: show(tree.orchard_id) },
            { title: tree.code, href: showTree(tree.id) },
        ],
    });

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title={tree.code} />
            <div className="flex items-start justify-between gap-2">
                <div className="space-y-2">
                    <Heading
                        title={tree.code}
                        description={[tree.orchard.name, tree.variety].filter(Boolean).join(' · ')}
                    />
                    {isRemoved && <Badge variant="secondary">ไม่อยู่แล้ว</Badge>}
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" size="icon" aria-label="เมนูต้นไม้">
                            <MoreVertical />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onSelect={() => setEditing(true)}>แก้ไข</DropdownMenuItem>
                        {isRemoved ? (
                            <DropdownMenuItem onSelect={() => router.patch(TreeController.restore.url(tree.id))}>
                                กู้คืนต้นไม้
                            </DropdownMenuItem>
                        ) : (
                            <DropdownMenuItem
                                onSelect={() => {
                                    if (confirm('บันทึกว่าต้นนี้ไม่อยู่แล้ว?')) {
                                        router.patch(TreeController.remove.url(tree.id));
                                    }
                                }}
                            >
                                ต้นไม้ไม่อยู่แล้ว
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <Dialog open={editing} onOpenChange={setEditing}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>แก้ไขต้นไม้</DialogTitle>
                        <DialogDescription>แก้ไขข้อมูลต้นไม้</DialogDescription>
                    </DialogHeader>
                    <Form
                        {...TreeController.update.form(tree.id)}
                        onSuccess={() => setEditing(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <TreeFormFields tree={tree} errors={errors} />
                                <Button type="submit" disabled={processing}>
                                    บันทึก
                                </Button>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            {tree.notes && <p className="text-sm">{tree.notes}</p>}

            {!isRemoved && (
                <div className="grid grid-cols-3 gap-2">
                    <Button
                        className="h-14 text-base"
                        onClick={() => setCareType('fertilizer')}
                    >
                        ใส่ปุ๋ย
                    </Button>
                    <Button
                        className="h-14 text-base"
                        onClick={() => setCareType('pesticide')}
                    >
                        ฉีดยา
                    </Button>
                    <Button className="h-14 text-base" disabled>
                        ผลผลิต
                    </Button>
                </div>
            )}

            <Dialog
                open={careType !== null}
                onOpenChange={(open) => !open && setCareType(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>บันทึกการดูแล</DialogTitle>
                        <DialogDescription>{tree.code}</DialogDescription>
                    </DialogHeader>
                    {careType && (
                        <Form
                            key={careType}
                            {...CareRecordController.store.form(tree.orchard_id)}
                            transform={(data) => ({
                                ...data,
                                tree_ids: [tree.id],
                                return_to: 'tree',
                            })}
                            onSuccess={() => setCareType(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <CareRecordFields
                                        defaultType={careType}
                                        errors={errors}
                                    />
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="h-12 w-full text-base"
                                    >
                                        บันทึก
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            {/* ประวัติ (timeline) และปุ่มผลผลิต — เพิ่มในงานถัดไป */}
        </div>
    );
}
