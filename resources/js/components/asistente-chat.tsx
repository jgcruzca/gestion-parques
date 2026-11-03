import { YakuMascot } from '@/components/yaku-mascot';
import { router } from '@inertiajs/react';
import { ArrowRight, ClipboardCheck, Download, FileText, Leaf, RotateCcw, Send, Trees, X } from 'lucide-react';
import { Component, type FormEvent, type ReactNode, useCallback, useEffect, useRef, useState } from 'react';

type Estado = 'completado' | 'requiere_aclaracion' | 'no_reconocido' | 'sin_resultados' | 'no_autorizado' | 'error';

interface RespuestaAsistente {
    estado: Estado;
    intencion: string | null;
    mensaje: string;
    lista?: string[];
    opciones?: string[];
    archivo?: { nombre: string; url: string };
    navegacion?: { ruta: string; etiqueta: string };
}

interface Mensaje {
    id: number;
    autor: 'usuario' | 'asistente';
    texto: string;
    estado?: Estado;
    lista?: string[];
    opciones?: string[];
    archivo?: { nombre: string; url: string };
    navegacion?: { ruta: string; etiqueta: string };
}

const ALMACEN = 'asistente-chat-yaku';
const MAX_MENSAJES = 60;
const MAX_CARACTERES = 500;

// Solo se aceptan destinos que el servidor ya conoce; nunca una dirección arbitraria.
const RUTAS_PERMITIDAS = [
    '/dashboard',
    '/parques',
    '/mantenimientos',
    '/trabajadores',
    '/reportes',
    '/configuracion',
    '/settings/profile',
    '/settings/password',
    '/settings/appearance',
];
const PREFIJO_DESCARGA = '/reportes/exportar/';
const PREFIJO_DOCUMENTO = '/mantenimientos/auditorias/';

const SUGERENCIAS = [
    { texto: '¿Qué falta mañana?', titulo: 'Lo que falta mañana', detalle: 'Trabajos pendientes y programados', Icono: ClipboardCheck },
    { texto: 'Dame los parques con punto de agua', titulo: 'Parques y agua', detalle: 'Puntos de agua y su estado', Icono: Trees },
    {
        texto: '¿Qué tipos de mantenimiento se hicieron esta semana?',
        titulo: 'Resumen por tipo',
        detalle: 'Cuántos trabajos de cada tipo',
        Icono: FileText,
    },
];

const MENSAJE_INICIAL: Mensaje = {
    id: 0,
    autor: 'asistente',
    texto: '¡Hola! Soy Yaku, tu asistente de áreas verdes. Te ayudo a consultar parques, revisar mantenimientos y preparar reportes. ¿Qué necesitas hoy?',
};

// La conversación empieza de nuevo cada día a las 00:00, hora de Lima (igual que los períodos del asistente).
const ZONA_HORARIA = 'America/Lima';
const REVISION_DIA_MS = 60_000;

function fechaLima(): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone: ZONA_HORARIA }).format(new Date());
}

function leerAlmacen(): { abierto: boolean; mensajes: Mensaje[]; reiniciar: boolean } {
    if (typeof window === 'undefined') {
        return { abierto: false, mensajes: [MENSAJE_INICIAL], reiniciar: false };
    }

    try {
        const guardado = window.sessionStorage.getItem(ALMACEN);

        if (guardado) {
            const datos = JSON.parse(guardado);

            if (Array.isArray(datos.mensajes)) {
                // Una conversación de otro día (o sin fecha) no se recupera.
                if (datos.fecha !== fechaLima()) {
                    return {
                        abierto: Boolean(datos.abierto),
                        mensajes: [MENSAJE_INICIAL],
                        reiniciar: Boolean(datos.reiniciar) || datos.mensajes.length > 1,
                    };
                }

                return { abierto: Boolean(datos.abierto), mensajes: datos.mensajes, reiniciar: Boolean(datos.reiniciar) };
            }
        }
    } catch {
        // El almacenamiento puede no estar disponible: el chat funciona igualmente.
    }

    // Sin historial visible (pestaña nueva) el servidor también empieza de cero.
    return { abierto: false, mensajes: [MENSAJE_INICIAL], reiniciar: true };
}

function tokenXsrf(): string {
    const coincidencia = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return coincidencia ? decodeURIComponent(coincidencia[1]) : '';
}

function rutaSegura(ruta: string | undefined): ruta is string {
    return typeof ruta === 'string' && RUTAS_PERMITIDAS.includes(ruta);
}

function descargaSegura(url: string | undefined): url is string {
    return typeof url === 'string' && (url.startsWith(PREFIJO_DESCARGA) || url.startsWith(PREFIJO_DOCUMENTO));
}

type Resultado = Pick<Mensaje, 'texto' | 'estado' | 'lista' | 'opciones' | 'archivo' | 'navegacion'> & { intencion?: string | null };

async function consultar(mensaje: string, reiniciar: boolean): Promise<Resultado> {
    const respuesta = await fetch('/asistente/consultar', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': tokenXsrf(),
        },
        body: JSON.stringify(reiniciar ? { mensaje, reiniciar: true } : { mensaje }),
    });

    if (respuesta.status === 401 || respuesta.status === 419) {
        return { estado: 'error', texto: 'Tu sesión expiró. Recarga la página e inicia sesión otra vez.' };
    }

    if (respuesta.status === 429) {
        return { estado: 'error', texto: 'Voy más rápido de lo que puedo responder. Espera un momento y vuelve a intentarlo.' };
    }

    let datos: Partial<RespuestaAsistente> = {};

    try {
        datos = await respuesta.json();
    } catch {
        return { estado: 'error', texto: 'No pude leer la respuesta. Inténtalo de nuevo.' };
    }

    return {
        estado: datos.estado ?? 'error',
        intencion: datos.intencion,
        texto:
            typeof datos.mensaje === 'string' && datos.mensaje !== '' ? datos.mensaje : 'No pude procesar tu solicitud esta vez. Inténtalo de nuevo.',
        lista: Array.isArray(datos.lista) ? datos.lista.filter((item) => typeof item === 'string') : undefined,
        opciones: Array.isArray(datos.opciones) ? datos.opciones.filter((item) => typeof item === 'string') : undefined,
        archivo: datos.archivo && descargaSegura(datos.archivo.url) ? datos.archivo : undefined,
        navegacion: datos.navegacion && rutaSegura(datos.navegacion.ruta) ? datos.navegacion : undefined,
    };
}

function AsistenteChatContenido() {
    const [abierto, setAbierto] = useState(false);
    const [mensajes, setMensajes] = useState<Mensaje[]>([MENSAJE_INICIAL]);
    const [cargado, setCargado] = useState(false);
    const [texto, setTexto] = useState('');
    const [enviando, setEnviando] = useState(false);
    const enCurso = useRef(false);
    const generacion = useRef(0);
    const siguienteId = useRef(1);
    const fechaChat = useRef(fechaLima());
    // Tras un cambio de día, el siguiente mensaje avisa al servidor para que olvide la aclaración pendiente.
    const reiniciarServidor = useRef(false);
    const lista = useRef<HTMLDivElement>(null);
    const campo = useRef<HTMLInputElement>(null);
    const boton = useRef<HTMLButtonElement>(null);

    // Al cerrar, el foco vuelve al botón que abrió el chat.
    const cerrar = useCallback(() => {
        setAbierto(false);
        window.requestAnimationFrame(() => boton.current?.focus({ preventScroll: true }));
    }, []);

    // El historial se recupera en el cliente: el layout se vuelve a montar en cada visita de Inertia.
    useEffect(() => {
        const guardado = leerAlmacen();

        setAbierto(guardado.abierto);
        setMensajes(guardado.mensajes);
        siguienteId.current = Math.max(0, ...guardado.mensajes.map((mensaje) => mensaje.id)) + 1;
        fechaChat.current = fechaLima();
        reiniciarServidor.current = guardado.reiniciar;
        setCargado(true);
    }, []);

    // Con la pestaña abierta al pasar la medianoche, la conversación empieza de nuevo.
    const reiniciarSiCambioElDia = useCallback(() => {
        const hoy = fechaLima();

        if (hoy === fechaChat.current) {
            return;
        }

        fechaChat.current = hoy;
        siguienteId.current = 1;
        reiniciarServidor.current = true;
        setMensajes([MENSAJE_INICIAL]);
    }, []);

    useEffect(() => {
        const intervalo = window.setInterval(reiniciarSiCambioElDia, REVISION_DIA_MS);

        return () => window.clearInterval(intervalo);
    }, [reiniciarSiCambioElDia]);

    useEffect(() => {
        if (abierto) {
            reiniciarSiCambioElDia();
        }
    }, [abierto, reiniciarSiCambioElDia]);

    useEffect(() => {
        if (!cargado) {
            return;
        }

        try {
            window.sessionStorage.setItem(
                ALMACEN,
                JSON.stringify({ fecha: fechaChat.current, abierto, mensajes: mensajes.slice(-MAX_MENSAJES), reiniciar: reiniciarServidor.current }),
            );
        } catch {
            // Sin almacenamiento disponible no se conserva el historial.
        }
    }, [abierto, mensajes, cargado]);

    useEffect(() => {
        if (abierto) {
            // Se desplaza solo el chat; scrollIntoView podría mover también la página.
            lista.current?.scrollTo({ top: lista.current.scrollHeight });
            campo.current?.focus({ preventScroll: true });
        }
    }, [abierto, mensajes, enviando]);

    useEffect(() => {
        if (!abierto) {
            return;
        }

        const cerrarConEscape = (evento: KeyboardEvent) => {
            if (evento.key === 'Escape') {
                cerrar();
            }
        };

        window.addEventListener('keydown', cerrarConEscape);

        return () => window.removeEventListener('keydown', cerrarConEscape);
    }, [abierto, cerrar]);

    const agregar = useCallback((mensaje: Omit<Mensaje, 'id'>) => {
        setMensajes((actuales) => [...actuales, { ...mensaje, id: siguienteId.current++ }]);
    }, []);

    const enviar = useCallback(
        async (contenido: string, textoVisible: string = contenido) => {
            const limpio = contenido.trim();

            // El cerrojo evita dos envíos simultáneos aunque el usuario pulse varias veces.
            if (limpio === '' || enCurso.current) {
                return;
            }

            reiniciarSiCambioElDia();
            enCurso.current = true;
            setEnviando(true);
            setTexto('');
            agregar({ autor: 'usuario', texto: textoVisible.trim() || limpio });

            try {
                const fechaEnvio = fechaChat.current;
                const envio = generacion.current;
                const reiniciar = reiniciarServidor.current;
                const { intencion, ...respuesta } = await consultar(limpio, reiniciar);

                // Solo se limpia la bandera si esta solicitud la llevaba: un reinicio posterior debe conservarla.
                if (reiniciar && respuesta.estado !== 'error') {
                    reiniciarServidor.current = false;
                }

                // La respuesta a un mensaje de un día anterior (o de una conversación ya reiniciada) no pertenece al chat actual.
                if (generacion.current !== envio || fechaChat.current !== fechaEnvio) {
                    return;
                }

                agregar({ autor: 'asistente', ...respuesta });

                // "Llévame a…" navega de inmediato; las demás respuestas solo ofrecen el botón.
                if (intencion === 'navegar_modulo' && respuesta.estado === 'completado' && respuesta.navegacion) {
                    router.visit(respuesta.navegacion.ruta);
                }
            } catch {
                agregar({
                    autor: 'asistente',
                    estado: 'error',
                    texto: 'No pude conectarme con el servidor. Revisa tu conexión y vuelve a intentarlo.',
                });
            } finally {
                enCurso.current = false;
                setEnviando(false);
            }
        },
        [agregar, reiniciarSiCambioElDia],
    );

    // Empieza de cero: limpia el historial visible y descarta la aclaración pendiente del servidor.
    const nuevaConversacion = useCallback(() => {
        if (enCurso.current) {
            return;
        }

        generacion.current += 1;
        fechaChat.current = fechaLima();
        siguienteId.current = 1;
        reiniciarServidor.current = true;
        setMensajes([MENSAJE_INICIAL]);
        setTexto('');
        campo.current?.focus({ preventScroll: true });
    }, []);

    const alEnviar = (evento: FormEvent) => {
        evento.preventDefault();
        void enviar(texto);
    };

    return (
        <div className="print:hidden">
            {!abierto && (
                <button
                    ref={boton}
                    type="button"
                    onClick={() => setAbierto(true)}
                    aria-label="Abrir el chat con Yaku"
                    className="group focus-visible:ring-primary fixed right-4 bottom-4 z-40 flex items-center rounded-full transition focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                >
                    <span className="bg-foreground text-background pointer-events-none absolute right-full mr-3 hidden rounded-lg px-2.5 py-1.5 text-[11px] font-semibold whitespace-nowrap opacity-0 shadow-lg transition group-hover:opacity-100 group-focus-visible:opacity-100 sm:block">
                        Habla con Yaku
                    </span>
                    <span className="relative grid size-14 place-items-center rounded-full bg-gradient-to-br from-emerald-500 to-teal-700 shadow-lg ring-1 shadow-emerald-950/25 ring-white/25 transition group-hover:-translate-y-0.5 group-hover:shadow-xl">
                        <span className="grid size-11 place-items-center rounded-full bg-emerald-50">
                            <YakuMascot className="size-9" animado />
                        </span>
                        <span className="dark:border-card absolute top-0 right-0 size-3.5 rounded-full border-2 border-white bg-lime-400" />
                    </span>
                </button>
            )}

            {abierto && (
                <section
                    role="dialog"
                    aria-label="Chat con Yaku, asistente de áreas verdes"
                    className="yaku-panel bg-card fixed inset-x-3 bottom-3 z-40 flex h-[calc(100dvh-1.5rem)] flex-col overflow-hidden rounded-2xl border shadow-2xl sm:inset-x-auto sm:right-6 sm:bottom-6 sm:h-[min(40rem,calc(100dvh-3rem))] sm:w-[26rem]"
                >
                    <header className="relative shrink-0 overflow-hidden bg-gradient-to-br from-emerald-700 via-emerald-800 to-teal-900 px-4 pt-4 pb-3.5 text-white">
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute -top-14 -right-10 size-40 rounded-full bg-white/10 blur-2xl"
                        />
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute -bottom-20 left-8 size-40 rounded-full bg-lime-300/15 blur-3xl"
                        />

                        <div className="relative flex items-center gap-3">
                            <span className="grid size-11 shrink-0 place-items-center rounded-full bg-emerald-50 shadow-inner ring-1 ring-white/30">
                                <YakuMascot className="size-9" animado />
                            </span>

                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <p className="truncate text-sm font-bold tracking-[-0.01em]">Yaku</p>
                                    <span className="inline-flex items-center gap-1 rounded-full bg-white/10 px-1.5 py-0.5 text-[9px] font-semibold tracking-[0.06em] text-emerald-50 uppercase">
                                        <span className="size-1 rounded-full bg-lime-300" />
                                        En línea
                                    </span>
                                </div>
                                <p className="mt-0.5 truncate text-[11px] text-emerald-50/90">Tu asistente de áreas verdes</p>
                            </div>

                            <div className="flex shrink-0 items-center gap-1">
                                <button
                                    type="button"
                                    onClick={nuevaConversacion}
                                    disabled={enviando}
                                    title="Nueva conversación"
                                    aria-label="Iniciar una nueva conversación"
                                    className="grid size-8 place-items-center rounded-lg text-emerald-50 transition hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    <RotateCcw className="size-4" />
                                </button>
                                <button
                                    type="button"
                                    onClick={cerrar}
                                    aria-label="Cerrar el chat"
                                    className="grid size-8 place-items-center rounded-lg text-emerald-50 transition hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                                >
                                    <X className="size-4" />
                                </button>
                            </div>
                        </div>
                    </header>

                    <div
                        ref={lista}
                        aria-live="polite"
                        aria-busy={enviando}
                        className="bg-background min-h-0 flex-1 space-y-3 overflow-y-auto px-3.5 py-4"
                    >
                        {mensajes.map((mensaje, indice) => (
                            <BurbujaMensaje
                                key={mensaje.id}
                                mensaje={mensaje}
                                onEnviar={(opcion, posicion) => void enviar(String(posicion + 1), opcion)}
                                interactivo={!enviando && mensaje.autor === 'asistente' && indice === mensajes.length - 1}
                            />
                        ))}

                        {mensajes.length <= 1 && !enviando && (
                            <div className="space-y-2 pt-1">
                                <p className="text-muted-foreground px-1 text-[9.5px] font-semibold tracking-[0.1em] uppercase">
                                    Empieza con una consulta
                                </p>

                                {SUGERENCIAS.map(({ texto: consulta, titulo, detalle, Icono }) => (
                                    <button
                                        key={consulta}
                                        type="button"
                                        onClick={() => void enviar(consulta)}
                                        className="group bg-card hover:border-primary/35 hover:bg-brand-soft/50 focus-visible:ring-primary/40 flex w-full items-center gap-3 rounded-xl border px-3 py-2.5 text-left shadow-[0_1px_2px_rgb(12_38_28/0.04)] transition focus-visible:ring-2 focus-visible:outline-none"
                                    >
                                        <span className="bg-brand-soft text-primary group-hover:bg-primary group-hover:text-primary-foreground grid size-8 shrink-0 place-items-center rounded-lg transition">
                                            <Icono className="size-4" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="text-foreground block truncate text-[12px] font-semibold">{titulo}</span>
                                            <span className="text-muted-foreground block truncate text-[11px]">{detalle}</span>
                                        </span>
                                        <ArrowRight className="text-muted-foreground/60 group-hover:text-primary size-3.5 shrink-0 transition group-hover:translate-x-0.5" />
                                    </button>
                                ))}

                                <p className="text-muted-foreground px-1 pt-1 text-[10px] leading-snug">
                                    Luego puedes afinar sin repetir todo: «y los vencidos», «y esta semana» o «en PDF».
                                </p>
                            </div>
                        )}

                        {enviando && <IndicadorEscribiendo />}
                    </div>

                    <form onSubmit={alEnviar} className="bg-card shrink-0 border-t p-3">
                        <div className="bg-background focus-within:border-primary focus-within:ring-primary/15 flex items-center gap-1.5 rounded-xl border px-2 py-1.5 transition focus-within:ring-2">
                            <label htmlFor="asistente-mensaje" className="sr-only">
                                Mensaje para Yaku
                            </label>
                            <input
                                id="asistente-mensaje"
                                ref={campo}
                                value={texto}
                                onChange={(evento) => setTexto(evento.target.value)}
                                maxLength={MAX_CARACTERES}
                                autoComplete="off"
                                enterKeyHint="send"
                                placeholder="Escribe tu consulta…"
                                className="text-foreground placeholder:text-muted-foreground min-w-0 flex-1 bg-transparent px-1.5 py-1 text-base outline-none sm:text-[13px]"
                            />
                            <button
                                type="submit"
                                disabled={enviando || texto.trim() === ''}
                                aria-label="Enviar mensaje"
                                className="bg-primary text-primary-foreground hover:bg-primary/90 grid size-8 shrink-0 place-items-center rounded-lg transition disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                {enviando ? (
                                    <span
                                        className="size-3 animate-spin rounded-full border-2 border-current border-t-transparent"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <Send className="size-3.5" />
                                )}
                            </button>
                        </div>

                        <div className="text-muted-foreground mt-1.5 flex items-center justify-between gap-2 px-1 text-[10px]">
                            <span className="flex min-w-0 items-center gap-1">
                                <Leaf className="size-3 shrink-0" />
                                <span className="truncate">Yaku responde con los datos del sistema</span>
                            </span>
                            {texto.length > 0 && (
                                <span className="shrink-0 tabular-nums">
                                    {texto.length}/{MAX_CARACTERES}
                                </span>
                            )}
                        </div>
                    </form>
                </section>
            )}
        </div>
    );
}

function BurbujaMensaje({
    mensaje,
    onEnviar,
    interactivo,
}: {
    mensaje: Mensaje;
    onEnviar: (opcion: string, posicion: number) => void;
    interactivo: boolean;
}) {
    if (mensaje.autor === 'usuario') {
        return (
            <div className="flex justify-end">
                <div className="bg-primary text-primary-foreground max-w-[85%] min-w-0 rounded-2xl rounded-br-md px-3.5 py-2.5 text-[12.5px] leading-relaxed shadow-sm">
                    {/* Siempre como texto: React escapa cualquier etiqueta HTML o script. */}
                    <p className="break-words whitespace-pre-wrap">{mensaje.texto}</p>
                </div>
            </div>
        );
    }

    const esError = mensaje.estado === 'error';
    const esAviso = mensaje.estado === 'no_autorizado' || mensaje.estado === 'no_reconocido' || mensaje.estado === 'sin_resultados';

    const tono = esError
        ? 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200'
        : esAviso
          ? 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200'
          : 'bg-card text-foreground border-border/70';

    return (
        <div className="flex items-start gap-2">
            <span className="bg-brand-soft ring-border/60 mt-0.5 grid size-7 shrink-0 place-items-center rounded-full ring-1">
                <YakuMascot className="size-5" />
            </span>

            <div className="max-w-[calc(100%-2.25rem)] min-w-0">
                <div
                    className={`rounded-2xl rounded-tl-md border px-3.5 py-2.5 text-[12.5px] leading-relaxed shadow-[0_1px_2px_rgb(12_38_28/0.04)] ${tono}`}
                >
                    <p className="break-words whitespace-pre-wrap">{mensaje.texto}</p>

                    {mensaje.lista && mensaje.lista.length > 0 && (
                        <ul className="border-border/60 mt-2.5 space-y-1.5 border-t pt-2.5">
                            {mensaje.lista.map((item, indice) => (
                                <li key={indice} className="flex items-start gap-1.5 text-[11.5px] leading-snug break-words">
                                    <Leaf className="text-primary mt-0.5 size-3 shrink-0" />
                                    <span className="min-w-0">{item}</span>
                                </li>
                            ))}
                        </ul>
                    )}

                    {mensaje.opciones && mensaje.opciones.length > 0 && (
                        <div className="border-border/60 mt-2.5 space-y-1.5 border-t pt-2.5">
                            <p className="text-muted-foreground text-[10px] font-semibold tracking-[0.06em] uppercase">Elige una opción</p>

                            {mensaje.opciones.map((opcion, indice) => (
                                <button
                                    key={`${opcion}-${indice}`}
                                    type="button"
                                    disabled={!interactivo}
                                    onClick={() => onEnviar(opcion, indice)}
                                    className="border-primary/25 bg-brand-soft/60 text-primary hover:bg-brand-soft focus-visible:ring-primary/40 flex w-full items-center justify-between gap-2 rounded-lg border px-2.5 py-1.5 text-left text-[11.5px] font-semibold transition focus-visible:ring-2 focus-visible:outline-none disabled:cursor-default disabled:opacity-60"
                                >
                                    <span className="min-w-0 break-words">{opcion}</span>
                                    <ArrowRight className="size-3 shrink-0" />
                                </button>
                            ))}
                        </div>
                    )}

                    {mensaje.archivo && (
                        <a
                            href={mensaje.archivo.url}
                            className="bg-primary text-primary-foreground hover:bg-primary/90 mt-2.5 flex items-center gap-2 rounded-xl px-3 py-2 text-[11px] font-semibold transition"
                        >
                            <Download className="size-3.5 shrink-0" />
                            <span className="min-w-0 break-all">Descargar {mensaje.archivo.nombre}</span>
                        </a>
                    )}

                    {mensaje.navegacion && (
                        <button
                            type="button"
                            onClick={() => router.visit(mensaje.navegacion!.ruta)}
                            className="border-primary/25 text-primary hover:bg-brand-soft focus-visible:ring-primary/40 mt-2.5 flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-[11px] font-semibold transition focus-visible:ring-2 focus-visible:outline-none"
                        >
                            {mensaje.navegacion.etiqueta}
                            <ArrowRight className="size-3.5" />
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}

function IndicadorEscribiendo() {
    return (
        <div className="flex items-start gap-2" role="status">
            <span className="bg-brand-soft ring-border/60 mt-0.5 grid size-7 shrink-0 place-items-center rounded-full ring-1">
                <YakuMascot className="size-5" />
            </span>

            <div className="bg-card border-border/70 flex items-center gap-2 rounded-2xl rounded-tl-md border px-3.5 py-2.5 shadow-[0_1px_2px_rgb(12_38_28/0.04)]">
                <span className="flex items-center gap-1" aria-hidden="true">
                    {[0, 1, 2].map((indice) => (
                        <span
                            key={indice}
                            className="yaku-typing-dot bg-primary size-1.5 rounded-full"
                            style={{ animationDelay: `${indice * 0.16}s` }}
                        />
                    ))}
                </span>
                <span className="text-muted-foreground text-[11px]">Yaku está consultando…</span>
            </div>
        </div>
    );
}

// Un fallo del chat nunca debe afectar a la página que lo contiene.
class LimiteDeErrores extends Component<{ children: ReactNode }, { fallo: boolean }> {
    state = { fallo: false };

    static getDerivedStateFromError() {
        return { fallo: true };
    }

    render() {
        return this.state.fallo ? null : this.props.children;
    }
}

export function AsistenteChat() {
    return (
        <LimiteDeErrores>
            <AsistenteChatContenido />
        </LimiteDeErrores>
    );
}
