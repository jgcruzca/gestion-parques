import { Head, useForm } from '@inertiajs/react';
import { Eye, EyeOff, LoaderCircle } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
}

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const [mostrarContrasena, setMostrarContrasena] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="Bienvenido de nuevo" description="Ingresa con tu cuenta institucional para continuar.">
            <Head title="Iniciar sesión" />

            <form className="flex flex-col gap-5" onSubmit={submit}>
                <div className="grid gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="email" className="text-[12px] font-semibold">
                            Correo electrónico
                        </Label>

                        <Input
                            id="email"
                            type="email"
                            required
                            autoFocus
                            autoComplete="email"
                            autoCapitalize="none"
                            spellCheck={false}
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="nombre@correo.com"
                            className="bg-background h-11 rounded-[10px] shadow-xs"
                        />

                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-1.5">
                        <div className="flex items-center gap-3">
                            <Label htmlFor="password" className="text-[12px] font-semibold">
                                Contraseña
                            </Label>

                            {canResetPassword && (
                                <TextLink href={route('password.request')} className="ml-auto text-[12px]">
                                    ¿Olvidaste tu contraseña?
                                </TextLink>
                            )}
                        </div>

                        <div className="relative">
                            <Input
                                id="password"
                                type={mostrarContrasena ? 'text' : 'password'}
                                required
                                autoComplete="current-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="Ingresa tu contraseña"
                                className="bg-background h-11 rounded-[10px] pr-11 shadow-xs"
                            />

                            <button
                                type="button"
                                onClick={() => setMostrarContrasena((visible) => !visible)}
                                aria-label={mostrarContrasena ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                                aria-pressed={mostrarContrasena}
                                className="text-muted-foreground hover:text-foreground absolute inset-y-0 right-0 grid w-11 place-items-center rounded-r-[10px] transition focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:outline-none"
                            >
                                {mostrarContrasena ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                            </button>
                        </div>

                        <InputError message={errors.password} />
                    </div>

                    <div className="flex items-center gap-2.5">
                        <Checkbox
                            id="remember"
                            name="remember"
                            checked={data.remember}
                            onCheckedChange={(checked) => setData('remember', checked === true)}
                        />

                        <Label htmlFor="remember" className="text-[12.5px] leading-none font-normal">
                            Recordarme en este equipo
                        </Label>
                    </div>

                    <Button
                        type="submit"
                        className="mt-2 h-11 w-full rounded-[10px] text-[13px] font-semibold shadow-[0_10px_24px_-16px_rgba(6,78,59,0.7)]"
                        disabled={processing}
                    >
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Iniciar sesión
                    </Button>
                </div>
            </form>

            {status && (
                <div
                    role="status"
                    className="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-center text-[12px] font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
                >
                    {status}
                </div>
            )}
        </AuthLayout>
    );
}
