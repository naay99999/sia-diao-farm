import { createInertiaApp } from '@inertiajs/react';
import { ConfirmProvider } from '@/components/confirm-dialog';
import { PasskeyPrompt } from '@/components/passkey-prompt';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'เสี่ยเดียวฟาร์ม';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                <ConfirmProvider>
                    {app}
                    <Toaster />
                    <PasskeyPrompt />
                </ConfirmProvider>
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});
