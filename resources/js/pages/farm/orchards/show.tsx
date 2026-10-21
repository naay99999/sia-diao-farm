import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import CareRecordController from '@/actions/App/Http/Controllers/Farm/CareRecordController';
import OrchardController from '@/actions/App/Http/Controllers/Farm/OrchardController';
import SaleController from '@/actions/App/Http/Controllers/Farm/SaleController';
import SeasonController from '@/actions/App/Http/Controllers/Farm/SeasonController';
import TreeController from '@/actions/App/Http/Controllers/Farm/TreeController';
import {
    CareRecordFields,
    TreePicker,
} from '@/components/farm/care-record-form';
import { formatPricePerKg, SaleFields } from '@/components/farm/sale-form';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import OrchardFormFields from '@/components/orchard-form-fields';
import SeasonFormFields from '@/components/season-form-fields';
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
import { formatThaiDate } from '@/lib/thai-date';
import { index, show } from '@/routes/orchards';
import { create as createCareRecord } from '@/routes/orchards/care-records';
import { create as createHarvest } from '@/routes/orchards/harvests';

import { show as showSeason } from '@/routes/seasons';
import { show as showTree } from '@/routes/trees';
import { careTypeLabels } from '@/types/farm';
import type { CareRecord, Orchard, Sale, Season, Tree } from '@/types/farm';

export default function OrchardShow({
    orchard,
    trees,
    showRemoved,
    currentSeason,
    seasons,
    recentCareRecords,
    activeTrees,
    sales,
    currentSeasonYieldKg,
}: {
    orchard: Orchard;
    trees: Tree[];
    showRemoved: boolean;
    currentSeason: Season | null;
    seasons: Season[];
    recentCareRecords: CareRecord[];
    activeTrees: Pick<Tree, 'id' | 'code'>[];
    sales: Sale[];
    currentSeasonYieldKg: Record<number, number>;
}) {
    const [saleDialog, setSaleDialog] = useState<Sale | 'new' | null>(null);
    const [editingCare, setEditingCare] = useState<CareRecord | null>(null);
    const [seasonDialog, setSeasonDialog] = useState<'start' | 'edit' | null>(
        null,
    );
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

            <section className="space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-lg font-medium">รอบการผลิต</h2>
                    {currentSeason ? (
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                onClick={() => setSeasonDialog('edit')}
                            >
                                แก้ไขรอบ
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    if (confirm('ปิดรอบนี้ตั้งแต่วันนี้?')) {
                                        router.patch(
                                            SeasonController.close.url(
                                                currentSeason.id,
                                            ),
                                        );
                                    }
                                }}
                            >
                                ปิดรอบ
                            </Button>
                        </div>
                    ) : (
                        <Button onClick={() => setSeasonDialog('start')}>
                            เริ่มรอบใหม่
                        </Button>
                    )}
                </div>
                {currentSeason ? (
                    <Link
                        href={showSeason(currentSeason.id)}
                        className="block rounded-lg border p-3 transition hover:border-foreground/20"
                    >
                        <p className="font-medium">{currentSeason.name}</p>
                        <p className="text-sm text-muted-foreground">
                            เริ่ม {formatThaiDate(currentSeason.started_on)} —
                            ยังไม่ปิดรอบ
                        </p>
                    </Link>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        ยังไม่มีรอบที่เปิดอยู่
                    </p>
                )}
                {seasons.length > 0 && (
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {seasons.map((season) => (
                            <Link
                                key={season.id}
                                href={showSeason(season.id)}
                                className="rounded-lg border p-3 transition hover:border-foreground/20"
                            >
                                <p className="font-medium">{season.name}</p>
                                <p className="text-sm text-muted-foreground">
                                    {formatThaiDate(season.started_on)} —{' '}
                                    {season.ended_on
                                        ? formatThaiDate(season.ended_on)
                                        : ''}
                                </p>
                            </Link>
                        ))}
                    </div>
                )}
                <Dialog
                    open={seasonDialog !== null}
                    onOpenChange={(open) => !open && setSeasonDialog(null)}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {seasonDialog === 'edit'
                                    ? 'แก้ไขรอบ'
                                    : 'เริ่มรอบใหม่'}
                            </DialogTitle>
                            <DialogDescription>
                                กำหนดชื่อและช่วงวันของรอบการผลิต
                            </DialogDescription>
                        </DialogHeader>
                        <Form
                            {...(seasonDialog === 'edit' && currentSeason
                                ? SeasonController.update.form(currentSeason.id)
                                : SeasonController.store.form(orchard.id))}
                            onSuccess={() => setSeasonDialog(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <SeasonFormFields
                                        season={
                                            seasonDialog === 'edit'
                                                ? (currentSeason ?? undefined)
                                                : undefined
                                        }
                                        errors={errors}
                                    />
                                    <Button type="submit" disabled={processing}>
                                        บันทึก
                                    </Button>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </section>

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
                    <Button variant="outline" asChild>
                        <Link href={createCareRecord(orchard.id)}>
                            บันทึกการดูแลหลายต้น
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={createHarvest(orchard.id)}>
                            บันทึกผลผลิตทั้งสวน
                        </Link>
                    </Button>
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
                        <div className="flex flex-col items-end gap-1">
                            {currentSeason && (
                                <p className="text-sm text-muted-foreground">
                                    {(
                                        currentSeasonYieldKg[tree.id] ?? 0
                                    ).toLocaleString('th-TH')}{' '}
                                    กก.
                                </p>
                            )}
                            {tree.removed_on && (
                                <Badge variant="secondary">ไม่อยู่แล้ว</Badge>
                            )}
                        </div>
                    </Link>
                ))}
            </div>

            <section className="space-y-3">
                <h2 className="text-lg font-medium">การดูแลล่าสุด</h2>
                {recentCareRecords.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        ยังไม่มีบันทึกการดูแล
                    </p>
                )}
                <div className="grid gap-2">
                    {recentCareRecords.map((record) => (
                        <div
                            key={record.id}
                            className="flex items-start justify-between gap-2 rounded-lg border p-3"
                        >
                            <div className="min-w-0">
                                <p className="font-medium">
                                    {careTypeLabels[record.type]}
                                    {record.product && ` · ${record.product}`}
                                    {record.quantity && ` · ${record.quantity}`}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {formatThaiDate(record.performed_on)} ·{' '}
                                    {record.trees?.length ?? 0} ต้น
                                </p>
                            </div>
                            <div className="flex shrink-0 gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setEditingCare(record)}
                                >
                                    แก้ไข
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => {
                                        if (confirm('ลบบันทึกนี้?')) {
                                            router.delete(
                                                CareRecordController.destroy.url(
                                                    record.id,
                                                ),
                                            );
                                        }
                                    }}
                                >
                                    ลบ
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
                <Dialog
                    open={editingCare !== null}
                    onOpenChange={(open) => !open && setEditingCare(null)}
                >
                    <DialogContent className="max-h-[90vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>แก้ไขบันทึกการดูแล</DialogTitle>
                            <DialogDescription>
                                แก้ไขรายละเอียดและต้นไม้ที่เกี่ยวข้อง
                            </DialogDescription>
                        </DialogHeader>
                        {editingCare && (
                            <Form
                                key={editingCare.id}
                                {...CareRecordController.update.form(
                                    editingCare.id,
                                )}
                                onSuccess={() => setEditingCare(null)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <CareRecordFields
                                            record={editingCare}
                                            errors={errors}
                                        />
                                        <TreePicker
                                            trees={activeTrees}
                                            attachedTrees={editingCare.trees}
                                            initialSelected={(
                                                editingCare.trees ?? []
                                            ).map((tree) => tree.id)}
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
                        )}
                    </DialogContent>
                </Dialog>
            </section>

            <section className="space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-lg font-medium">การขาย</h2>
                    <Button onClick={() => setSaleDialog('new')}>
                        บันทึกการขาย
                    </Button>
                </div>
                {sales.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        ยังไม่มีบันทึกการขาย
                    </p>
                )}
                <div className="grid gap-2">
                    {sales.map((sale) => (
                        <div
                            key={sale.id}
                            className="flex items-start justify-between gap-2 rounded-lg border p-3"
                        >
                            <div className="min-w-0">
                                <p className="font-medium">
                                    {Number(sale.weight_kg).toLocaleString(
                                        'th-TH',
                                    )}{' '}
                                    กก. ·{' '}
                                    {Number(sale.amount).toLocaleString(
                                        'th-TH',
                                    )}{' '}
                                    บาท
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {formatThaiDate(sale.sold_on)}
                                    {sale.buyer_name &&
                                        ` · ${sale.buyer_name}`}{' '}
                                    · {formatPricePerKg(sale)} บาท/กก.
                                </p>
                            </div>
                            <div className="flex shrink-0 gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setSaleDialog(sale)}
                                >
                                    แก้ไข
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => {
                                        if (confirm('ลบการขายนี้?')) {
                                            router.delete(
                                                SaleController.destroy.url(
                                                    sale.id,
                                                ),
                                            );
                                        }
                                    }}
                                >
                                    ลบ
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
                <Dialog
                    open={saleDialog !== null}
                    onOpenChange={(open) => !open && setSaleDialog(null)}
                >
                    <DialogContent className="max-h-[90vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>
                                {saleDialog === 'new'
                                    ? 'บันทึกการขาย'
                                    : 'แก้ไขการขาย'}
                            </DialogTitle>
                            <DialogDescription>
                                ระบุน้ำหนักและจำนวนเงินที่ขายได้
                            </DialogDescription>
                        </DialogHeader>
                        {saleDialog && (
                            <Form
                                key={
                                    saleDialog === 'new' ? 'new' : saleDialog.id
                                }
                                {...(saleDialog === 'new'
                                    ? SaleController.store.form(orchard.id)
                                    : SaleController.update.form(
                                          saleDialog.id,
                                      ))}
                                onSuccess={() => setSaleDialog(null)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <SaleFields
                                            sale={
                                                saleDialog === 'new'
                                                    ? undefined
                                                    : saleDialog
                                            }
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
                        )}
                    </DialogContent>
                </Dialog>
            </section>
        </div>
    );
}
