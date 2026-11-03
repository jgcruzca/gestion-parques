import { Link } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="relative min-h-svh overflow-hidden bg-[#061e15]">
            <div
                aria-hidden="true"
                className="absolute inset-0 bg-[radial-gradient(ellipse_at_20%_20%,#14532d_0%,#0b3b26_45%,#061e15_100%)]"
            />
            <div
                aria-hidden="true"
                className="absolute inset-0 bg-[linear-gradient(100deg,rgba(4,31,21,0.9)_0%,rgba(4,31,21,0.7)_46%,rgba(4,31,21,0.18)_78%,rgba(4,31,21,0.08)_100%)]"
            />

            <div className="relative grid min-h-svh lg:grid-cols-[1.06fr_0.94fr]">
                <section className="auth-brand flex min-h-[19rem] flex-col justify-between px-6 py-7 text-white sm:min-h-[22rem] sm:px-10 sm:py-9 lg:sticky lg:top-0 lg:h-svh lg:px-14 lg:py-12 xl:px-20">
                    <Link
                        href={route('home')}
                        aria-label="Ir al inicio"
                        className="inline-flex w-fit items-center gap-3 rounded-xl focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-4 focus-visible:ring-offset-[#061e15] focus-visible:outline-none"
                    >
                        <span className="grid size-12 place-items-center rounded-xl bg-white shadow-[0_12px_28px_-14px_rgba(0,0,0,0.65)]">
                            <img
                                src="/images/logo-sistema-verde.svg"
                                alt="Sistema Verde"
                                className="size-10 object-contain"
                            />
                        </span>
                        <span className="hidden text-[11px] leading-tight font-semibold tracking-[0.08em] text-white/70 uppercase sm:block">
                            Sistema Verde
                            <br />
                            Parques y mantenimiento
                        </span>
                    </Link>

                    <div className="mt-10 max-w-[34rem] lg:mt-0">
                        <div aria-hidden="true" className="mb-5 h-px w-14 bg-amber-300/80" />
                        <h1 className="text-[clamp(2rem,4.4vw,3.45rem)] leading-[1.04] font-semibold tracking-[-0.035em] text-balance">
                            Sistema Verde
                        </h1>
                        <p className="mt-5 max-w-[30rem] text-[13.5px] leading-relaxed text-white/70 sm:text-sm">
                            Gestiona parques, áreas verdes, cuadrillas y mantenimientos desde un solo lugar. Cada cuenta tiene su propio espacio privado.
                        </p>
                    </div>

                    <div className="mt-10 flex items-end justify-between gap-4 text-[11px] text-white/70 lg:mt-0">
                        <span>Espacio privado por cuenta</span>
                        <span className="text-right">Sistema Verde</span>
                    </div>
                </section>

                <section className="auth-panel flex min-h-[calc(100svh-19rem)] items-start justify-center border-t border-white/10 bg-[#f7f5ef] px-5 py-9 sm:px-8 lg:h-svh lg:min-h-svh lg:overflow-y-auto lg:border-t-0 lg:border-l lg:border-black/5 lg:px-10 lg:py-12 dark:bg-[#0a1711] dark:lg:border-white/10">
                    <div className="my-auto w-full max-w-[408px]">
                        <div className="mb-8">
                            <h2 className="text-foreground text-[26px] leading-tight font-semibold tracking-[-0.025em] text-balance">{title}</h2>
                            <p className="text-muted-foreground mt-2.5 text-[13px] leading-relaxed">{description}</p>
                        </div>

                        {children}

                        <div className="mt-9 border-t pt-5">
                            <p className="text-muted-foreground text-[11px] leading-relaxed">
                                Tus datos son privados y solo visibles desde tu cuenta.
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    );
}
