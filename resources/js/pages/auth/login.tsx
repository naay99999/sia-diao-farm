import { Form, Head } from '@inertiajs/react';
import { UserCancelledError } from '@laravel/passkeys';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { Fingerprint } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { login as passkeyLogin, loginOptions } from '@/routes/passkey';

type Props = {
    status?: string;
};

const DEACTIVATED_MESSAGE = 'บัญชีนี้ถูกปิดใช้งานแล้ว';
const PASSKEY_FAILED_MESSAGE = 'เข้าสู่ระบบด้วย passkey ไม่สำเร็จ ลองอีกครั้ง';

export default function Login({ status }: Props) {
    const { verify, isLoading, errorInstance, isSupported } = usePasskeyVerify({
        routes: {
            options: loginOptions.url(),
            // The package cannot send `remember`; the server reads it from the query string.
            submit: passkeyLogin.url({ query: { remember: 1 } }),
        },
        onSuccess: (response) => {
            window.location.href = response.redirect ?? '/';
        },
    });

    const passkeyError =
        errorInstance === null || errorInstance instanceof UserCancelledError
            ? undefined
            : errorInstance.message === DEACTIVATED_MESSAGE
              ? DEACTIVATED_MESSAGE
              : PASSKEY_FAILED_MESSAGE;

    return (
        <>
            <Head title="เข้าสู่ระบบ" />

            {isSupported && (
                <div className="mb-6 grid gap-3">
                    <Button
                        type="button"
                        className="w-full"
                        disabled={isLoading}
                        onClick={() => verify()}
                        data-test="passkey-login-button"
                    >
                        {isLoading ? <Spinner /> : <Fingerprint />}
                        เข้าสู่ระบบด้วย passkey
                    </Button>
                    <InputError message={passkeyError} />

                    <div className="relative mt-3 flex items-center justify-center">
                        <div className="absolute inset-x-0 border-t" />
                        <span className="relative bg-background px-3 text-sm text-muted-foreground">
                            หรือใช้รหัสผ่าน
                        </span>
                    </div>
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">อีเมล</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="อีเมลของบัญชีเจ้าของสวน"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">รหัสผ่าน</Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="รหัสผ่าน"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">จดจำฉัน</Label>
                            </div>

                            <Button
                                type="submit"
                                variant="outline"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                เข้าสู่ระบบ
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-base font-medium text-muted-foreground md:text-sm">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'เข้าสู่ระบบ',
    description: 'สแกนนิ้วหรือใบหน้าด้วย passkey หรือกรอกอีเมลและรหัสผ่าน',
};
