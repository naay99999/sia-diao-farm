import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { Sale } from '@/types/farm';
import { todayIso } from './care-record-form';

type Errors = Record<string, string | undefined>;

export function formatPricePerKg(
    sale: Pick<Sale, 'amount' | 'weight_kg'>,
): string {
    const weight = Number(sale.weight_kg);

    return weight > 0
        ? (Number(sale.amount) / weight).toLocaleString('th-TH', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          })
        : '-';
}

export function SaleFields({ sale, errors }: { sale?: Sale; errors: Errors }) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="sold_on">วันที่ขาย</Label>
                <Input
                    id="sold_on"
                    name="sold_on"
                    type="date"
                    required
                    defaultValue={sale?.sold_on.slice(0, 10) ?? todayIso()}
                />
                <InputError message={errors.sold_on} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="buyer_name">ผู้ซื้อ</Label>
                <Input
                    id="buyer_name"
                    name="buyer_name"
                    defaultValue={sale?.buyer_name ?? ''}
                />
                <InputError message={errors.buyer_name} />
            </div>
            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="sale-weight">น้ำหนัก (กก.)</Label>
                    <Input
                        id="sale-weight"
                        name="weight_kg"
                        inputMode="decimal"
                        required
                        defaultValue={sale?.weight_kg ?? ''}
                    />
                    <InputError message={errors.weight_kg} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="sale-amount">จำนวนเงิน (บาท)</Label>
                    <Input
                        id="sale-amount"
                        name="amount"
                        inputMode="decimal"
                        required
                        defaultValue={sale?.amount ?? ''}
                    />
                    <InputError message={errors.amount} />
                </div>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="sale-notes">หมายเหตุ</Label>
                <Textarea
                    id="sale-notes"
                    name="notes"
                    rows={3}
                    defaultValue={sale?.notes ?? ''}
                />
                <InputError message={errors.notes} />
            </div>
        </>
    );
}
