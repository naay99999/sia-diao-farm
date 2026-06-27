import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="ตั้งค่าการแสดงผล" />

            <h1 className="sr-only">ตั้งค่าการแสดงผล</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="ตั้งค่าการแสดงผล"
                    description="ปรับการแสดงผลของบัญชีของคุณ"
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'ตั้งค่าการแสดงผล',
            href: editAppearance(),
        },
    ],
};
