import { useRef, useState, type KeyboardEvent, type ReactNode } from "react";
import { Toaster } from "react-hot-toast";
import { CopyToClipboard } from "@/js/Component/CopyToClipboard";
import { createMcpCredentials, notifications, saveMcpAccess } from "@/js/Utils/Data";
import {
    AUTH_PLACEHOLDER,
    MCP_CLIENTS,
    PASSWORD_PLACEHOLDER,
    basicCredentials,
    detectOs,
    encodeCommand,
    manualConfig,
    type McpOs,
    MCP_DISABLED_MESSAGE,
    TEST_PROMPT,
    adapterBadge,
    clientConfig,
    connectionState,
    mcpClient,
    setupPrompt,
    type McpClientId,
    type McpCredentials,
} from "@/js/Utils/mcpConnect";

/** Card surface shared by the page's sections. */
const CARD = 'rounded-xl border border-slate-200 bg-white shadow-sm';

/** A small status pill. */
function Badge({ tone, children }: { tone: 'green' | 'amber' | 'slate'; children: ReactNode }) {
    const tones = {
        green: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        amber: 'bg-amber-50 text-amber-800 ring-amber-600/20',
        slate: 'bg-slate-100 text-slate-700 ring-slate-500/20',
    };
    const dots = { green: 'bg-emerald-500', amber: 'bg-amber-500', slate: 'bg-slate-400' };
    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${tones[tone]}`}>
            <span className={`h-1.5 w-1.5 rounded-full ${dots[tone]}`} aria-hidden="true" />
            {children}
        </span>
    );
}

/** A numbered step inside the connection card. */
function Step({ number, title, description, children }: { number: number; title: string; description?: ReactNode; children: ReactNode }) {
    return (
        <div className="flex gap-4">
            <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white" aria-hidden="true">
                {number}
            </span>
            <div className="min-w-0 flex-1">
                <h3 className="m-0 text-sm font-semibold text-slate-900">{title}</h3>
                {description && <p className="mt-1 mb-0 text-[13px] leading-relaxed text-slate-600">{description}</p>}
                <div className="mt-3">{children}</div>
            </div>
        </div>
    );
}

/**
 * A code block with a copy button that copies the whole text. Prompts wrap;
 * configurations keep their lines and scroll sideways.
 */
function CodeBox({ text, placeholder, copyLabel, scroll = false, minHeight = 'min-h-[200px]', highlight = '' }: { text: string; placeholder?: string; copyLabel: string; scroll?: boolean; minHeight?: string; highlight?: string }) {
    return (
        <div className="relative w-full min-w-0 overflow-hidden rounded-lg border border-slate-800 bg-slate-900">
            {text && (
                <div className="absolute top-2 right-2 rounded-md bg-slate-800">
                    <CopyToClipboard text={text} label={copyLabel} className="hover:bg-slate-700 [&_svg]:text-slate-200" />
                </div>
            )}
            <pre className={`m-0 max-h-[420px] overflow-y-auto p-4 pr-12 font-mono text-xs leading-relaxed ${minHeight} ${scroll ? 'whitespace-pre overflow-x-auto' : 'whitespace-pre-wrap break-words'} ${text ? 'text-slate-100' : 'text-slate-400'}`}>
                {text ? <Highlighted text={text} part={highlight} /> : placeholder}
            </pre>
        </div>
    );
}

/** Text with every occurrence of `part` marked (only the display; copying uses the plain text). */
function Highlighted({ text, part }: { text: string; part: string }) {
    if (!part || !text.includes(part)) return <>{text}</>;
    return (
        <>
            {text.split(part).map((piece, index) => (
                <span key={index}>
                    {index > 0 && <mark className="rounded-sm bg-amber-200 px-0.5 text-slate-900">{part}</mark>}
                    {piece}
                </span>
            ))}
        </>
    );
}

/** One operating system's encoding command, with a copy button. */
function CommandCard({ os, title, help, command, current }: { os: McpOs; title: string; help: string; command: string; current: boolean }) {
    return (
        <div className={`overflow-hidden rounded-lg bg-slate-900 ${current ? 'ring-2 ring-blue-600 ring-offset-2' : ''}`} data-os={os}>
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-700 bg-slate-800 px-4 py-2">
                <div className="flex items-center gap-2">
                    <span className="text-[13px] font-semibold text-white">{title}</span>
                    {current && <span className="rounded-full bg-blue-600 px-2 py-0.5 text-[11px] font-semibold text-white">Your computer</span>}
                </div>
                <CopyToClipboard text={command} label="Copy command" showLabel className="bg-white text-slate-900 hover:bg-slate-200 [&_svg]:h-3.5 [&_svg]:w-3.5 [&_svg]:text-slate-700" />
            </div>
            <pre className="m-0 overflow-x-auto px-4 pt-3 pb-1 font-mono text-[13px] leading-relaxed whitespace-pre-wrap break-all text-slate-100">
                <Highlighted text={command} part={PASSWORD_PLACEHOLDER} />
            </pre>
            <p className="m-0 px-4 pt-1 pb-3 text-xs leading-relaxed text-slate-300">{help}</p>
        </div>
    );
}

/** A URL with a copy button. */
function UrlBox({ url }: { url: string }) {
    return (
        <div className="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 py-1.5 pr-1.5 pl-3">
            <code className="min-w-0 flex-1 break-all bg-transparent p-0 font-mono text-[13px] text-slate-800">{url}</code>
            <CopyToClipboard text={url} label="Copy URL" />
        </div>
    );
}

/** An expandable row: a button header and the region it controls. */
function Disclosure({ id, title, description, open, onToggle, children }: { id: string; title: string; description: string; open: boolean; onToggle: () => void; children: ReactNode }) {
    return (
        <div className="border-t border-slate-200 first:border-t-0">
            <button
                type="button"
                id={`${id}-toggle`}
                className="flex w-full cursor-pointer items-center justify-between gap-4 border-0 bg-transparent px-5 py-4 text-left hover:bg-slate-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-blue-600"
                aria-expanded={open}
                aria-controls={id}
                onClick={onToggle}
            >
                <span className="min-w-0">
                    <span className="block text-sm font-semibold text-slate-900">{title}</span>
                    <span className="mt-0.5 block text-[13px] text-slate-600">{description}</span>
                </span>
                <svg className={`h-4 w-4 shrink-0 text-slate-500 transition-transform ${open ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            {open && (
                <div id={id} role="region" aria-labelledby={`${id}-toggle`} className="px-5 pb-5">
                    {children}
                </div>
            )}
        </div>
    );
}

/** A small spinner for buttons. */
function Spinner() {
    return (
        <svg className="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
    );
}

/**
 * The Connection tab of Media › MCP Connection: the MCP access switch, the
 * WordPress default MCP server connection (choose a client, generate a setup
 * prompt or use the manual configuration, test it) and, under Advanced
 * configuration, the dedicated endpoint. While access is turned off only
 * the switch and a message are shown. The MCP Adapter notice is rendered by
 * the page above the tabs, not here.
 */
export default function McpPage() {
    const [settings, setSettings] = useState<McpPageSettings | null>(tsmltParams.mcp ?? null);
    const [saving, setSaving] = useState(false);
    const [client, setClient] = useState<McpClientId>('claude-code');
    const [dedicated, setDedicated] = useState(false);
    const [consent, setConsent] = useState(false);
    const [creating, setCreating] = useState(false);
    const [error, setError] = useState('');
    const [generatedFor, setGeneratedFor] = useState<McpClientId | null>(null);
    const [authUser, setAuthUser] = useState(tsmltParams.mcp?.username ?? '');
    const [authPassword, setAuthPassword] = useState('');
    const [encoded, setEncoded] = useState('');
    const [credentials, setCredentials] = useState<Partial<Record<McpClientId, McpCredentials>>>({});
    const tabs = useRef<Array<HTMLButtonElement | null>>([]);
    const os = detectOs(typeof navigator === 'undefined' ? '' : `${navigator.platform} ${navigator.userAgent}`);

    if (!settings) {
        return <p className="py-6 text-sm text-slate-600">MCP settings are not available.</p>;
    }

    const current = mcpClient(client);
    const creds = credentials[client];
    const state = connectionState(settings, consent, creating);
    const badge = adapterBadge(settings);
    const clientName = client === 'other' ? 'your AI tool' : current.label;
    const action = 'Generate Prompt';
    // The generated credentials when there are some, otherwise a template
    // with the placeholder (or the value made in "Or create it here").
    const manualText = creds ? clientConfig(client, creds) : manualConfig(client, settings.serverUrl, settings.serverName, encoded);
    const createAuth = () => {
        if (!authUser.trim() || !authPassword.trim()) return;
        setEncoded(basicCredentials(authUser.trim(), authPassword.trim()));
        // The password is not kept once the value is made.
        setAuthPassword('');
    };
    const manualHelp = current.format === 'shell'
        ? 'Run this command in your own terminal, then run "claude mcp list" to check the connection.'
        : current.configPath.unix
            ? `Add this to ${current.configPath.unix} (macOS/Linux) or ${current.configPath.windows} (Windows), keeping any servers already there, then restart ${current.label}.`
            : 'Add this server to your client\'s MCP configuration (most clients accept this format), then restart the client.';

    const toggleAccess = async (disabled: boolean) => {
        setSaving(true);
        const next = await saveMcpAccess(disabled);
        setSaving(false);
        if (next) {
            setSettings(next);
            notifications(true, disabled ? 'MCP access is turned off.' : 'MCP access is turned on.');
        } else {
            notifications(false, 'The setting could not be saved.');
        }
    };

    const generate = async () => {
        if (!state.canGenerate) return;
        setCreating(true);
        setError('');
        setGeneratedFor(null);
        const result = await createMcpCredentials(client);
        setCreating(false);
        if (result.credentials) {
            setCredentials({ ...credentials, [client]: result.credentials });
            // Each new password needs a new confirmation.
            setConsent(false);
            setGeneratedFor(client);
            notifications(true, 'Setup prompt generated.');
        } else {
            setError(result.error || 'The Application Password could not be created.');
        }
    };

    const selectClient = (id: McpClientId) => {
        setClient(id);
        setError('');
        setConsent(false);
        setGeneratedFor(null);
    };

    // Arrow keys, Home and End move between client tabs (WAI-ARIA tabs pattern).
    const onTabKey = (event: KeyboardEvent<HTMLButtonElement>, index: number) => {
        const last = MCP_CLIENTS.length - 1;
        const keys: Record<string, number> = { ArrowRight: index === last ? 0 : index + 1, ArrowLeft: index === 0 ? last : index - 1, Home: 0, End: last };
        if (!(event.key in keys)) return;
        event.preventDefault();
        const next = keys[event.key];
        selectClient(MCP_CLIENTS[next].id);
        tabs.current[next]?.focus();
    };

    return (
        <div className="tsmlt-mcp-app space-y-6 pt-6 pb-12">
            <Toaster position="top-right" />

            {/* MCP access */}
            <section className={`${CARD} flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between`} aria-labelledby="tsmlt-mcp-access-title">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 id="tsmlt-mcp-access-title" className="m-0 text-base font-semibold text-slate-900">MCP access</h2>
                        {settings.enabled ? <Badge tone="green">Enabled</Badge> : <Badge tone="slate">Disabled</Badge>}
                    </div>
                    <p className="mt-1 mb-0 max-w-2xl text-[13px] leading-relaxed text-slate-600">
                        Turning access off refuses every Media Library Tools tool and resource on both MCP servers. Connected clients keep their settings and work again when you turn it back on. Other plugins' MCP tools are not affected.
                    </p>
                </div>
                <label htmlFor="tsmlt-mcp-access-off" className="flex shrink-0 cursor-pointer items-center gap-3 select-none">
                    <span className="text-sm font-medium text-slate-800">
                        Turn off MCP access {saving && <span className="font-normal text-slate-500">(saving…)</span>}
                    </span>
                    <span className="relative inline-flex">
                        <input
                            id="tsmlt-mcp-access-off"
                            type="checkbox"
                            role="switch"
                            className="peer sr-only"
                            checked={!settings.enabled}
                            disabled={saving}
                            onChange={(event) => toggleAccess(event.target.checked)}
                        />
                        <span className="h-6 w-11 rounded-full bg-slate-300 transition-colors peer-checked:bg-slate-900 peer-focus-visible:ring-2 peer-focus-visible:ring-blue-600 peer-focus-visible:ring-offset-2 peer-disabled:opacity-60" aria-hidden="true" />
                        <span className="pointer-events-none absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5" aria-hidden="true" />
                    </span>
                </label>
            </section>

            {!state.showConnection ? (
                <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-5" role="status">
                    <svg className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    <p className="m-0 text-sm text-amber-900">{MCP_DISABLED_MESSAGE}</p>
                </div>
            ) : (
                <>
                    {/* Primary connection: the WordPress default MCP server */}
                    <section className={CARD} aria-labelledby="tsmlt-mcp-connect-title">
                        <div className="border-b border-slate-200 p-5">
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 id="tsmlt-mcp-connect-title" className="m-0 text-base font-semibold text-slate-900">Connect an AI client</h2>
                                <Badge tone={badge.tone}>{badge.label}</Badge>
                            </div>
                            <p className="mt-1 mb-4 max-w-3xl text-[13px] leading-relaxed text-slate-600">
                                Connect through the WordPress default MCP server: one connection for Media Library Tools and the other WordPress and plugin MCP abilities on this site.
                            </p>
                            {state.defaultAvailable ? (
                                <UrlBox url={settings.serverUrl} />
                            ) : (
                                <p className="m-0 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-2.5 text-[13px] text-slate-700">
                                    This connection is not available yet: it needs the MCP Adapter plugin installed and active. Use the notice above, then return to this page.
                                </p>
                            )}
                        </div>

                        <div className="space-y-8 p-5">
                            <Step number={1} title="Choose your client">
                                <div className="flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1" role="tablist" aria-label="MCP client">
                                    {MCP_CLIENTS.map((item, index) => (
                                        <button
                                            key={item.id}
                                            ref={(element) => { tabs.current[index] = element; }}
                                            type="button"
                                            role="tab"
                                            id={`tsmlt-mcp-client-${item.id}`}
                                            aria-selected={client === item.id}
                                            aria-controls="tsmlt-mcp-client-panel"
                                            tabIndex={client === item.id ? 0 : -1}
                                            className={`flex-1 cursor-pointer whitespace-nowrap rounded-md border-0 px-3 py-1.5 text-[13px] font-medium transition-colors focus-visible:outline-2 focus-visible:outline-blue-600 sm:flex-none sm:px-4 ${client === item.id ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200' : 'bg-transparent text-slate-600 hover:text-slate-900'}`}
                                            onClick={() => selectClient(item.id)}
                                            onKeyDown={(event) => onTabKey(event, index)}
                                        >
                                            {item.label}
                                        </button>
                                    ))}
                                </div>
                            </Step>

                            <div id="tsmlt-mcp-client-panel" role="tabpanel" aria-labelledby={`tsmlt-mcp-client-${client}`} className="space-y-8">
                                <Step
                                    number={2}
                                    title="Generate the setup prompt"
                                    description={<>Paste the prompt into {clientName} and it adds the connection itself. An Application Password is created for it, which you can revoke any time in <a href={settings.profileUrl} className="text-blue-700 underline">your profile</a>. The prompt's Authorization header holds the password, so it is sent to your AI tool; to avoid that, use the manual configuration below.</>}
                                >
                                    <div className="flex flex-col gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="flex items-start gap-2.5">
                                            <input
                                                id="tsmlt-mcp-consent"
                                                type="checkbox"
                                                className="mt-0.5 h-4 w-4 shrink-0 cursor-pointer"
                                                checked={consent}
                                                onChange={(event) => setConsent(event.target.checked)}
                                            />
                                            <label htmlFor="tsmlt-mcp-consent" className="cursor-pointer text-[13px] leading-snug text-slate-700">
                                                I understand that clicking <strong>{action}</strong> will create an application password for this site.
                                            </label>
                                        </div>
                                        <button
                                            type="button"
                                            className="inline-flex h-9 shrink-0 cursor-pointer items-center justify-center gap-2 rounded-lg border-0 bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600 disabled:shadow-none"
                                            onClick={generate}
                                            disabled={!state.canGenerate}
                                        >
                                            {creating && <Spinner />}
                                            {creating ? 'Generating…' : (creds ? `${action} Again` : action)}
                                        </button>
                                    </div>
                                    {state.blocker && <p className="mt-3 mb-0 text-[13px] text-amber-800">{state.blocker}</p>}
                                    {error && <p className="mt-3 mb-0 rounded-lg bg-red-50 px-3 py-2 text-[13px] text-red-700" role="alert">{error}</p>}
                                    {generatedFor === client && creds && (
                                        <p className="mt-3 mb-0 rounded-lg bg-emerald-50 px-3 py-2 text-[13px] text-emerald-800" role="status">
                                            Prompt ready. Copy it and paste it into {clientName}.
                                        </p>
                                    )}
                                    <div className="mt-4">
                                        <CodeBox
                                            text={creds ? setupPrompt(client, creds) : ''}
                                            placeholder="Your setup prompt will appear here after you generate it."
                                            copyLabel="Copy prompt"
                                        />
                                    </div>
                                </Step>

                                {/* Manual configuration, directly under the prompt */}
                                <div id="tsmlt-mcp-manual" role="region" aria-labelledby="tsmlt-mcp-manual-title" className="rounded-lg border border-slate-200 p-5 sm:ml-10">
                                    <h3 id="tsmlt-mcp-manual-title" className="m-0 text-sm font-semibold text-slate-900">Manual configuration</h3>
                                    <p className="mt-0.5 mb-3 text-[13px] text-slate-600">
                                        Prefer to set it up yourself? Use the {current.label} configuration below instead of the prompt; no Application Password is created for you.
                                    </p>
                                    <p className="mt-0 mb-1 text-[13px] leading-relaxed text-slate-700">
                                        {manualHelp}{current.requiresNode && ' It needs Node.js 18 or newer.'}
                                    </p>
                                    <p className="mt-0 mb-3 text-xs leading-relaxed text-slate-500">
                                        {creds
                                            ? 'Filled in with the Application Password created with the prompt. Keep it private.'
                                            : encoded
                                                ? 'Filled in with the Authorization value you created below. Keep it private.'
                                                : `Replace ${AUTH_PLACEHOLDER} with your Authorization value; see below how to create it.`}
                                    </p>
                                    <CodeBox
                                        text={manualText}
                                        copyLabel="Copy configuration"
                                        scroll
                                        minHeight="min-h-[96px]"
                                        highlight={AUTH_PLACEHOLDER}
                                    />

                                    {!creds && (
                                        <div className="mt-5 rounded-lg border border-blue-100 bg-blue-50/60 p-5" aria-labelledby="tsmlt-mcp-auth-title" role="group">
                                            <h4 id="tsmlt-mcp-auth-title" className="m-0 text-sm font-semibold text-slate-900">How to create the Authorization value</h4>
                                            <p className="mt-1 mb-4 text-[13px] leading-relaxed text-slate-700">
                                                Combine your username and an Application Password as <code className="rounded bg-white px-1 text-xs">username:password</code>, encode it in Base64, and put it after "Authorization: Basic ".
                                            </p>
                                            <ol className="m-0 list-decimal space-y-4 pl-5 text-[13px] leading-relaxed text-slate-700">
                                                <li>
                                                    <a href={settings.profileUrl} className="font-semibold text-blue-700 underline">Create an application password</a>. Copy it exactly as shown, spaces included.
                                                </li>
                                                <li>
                                                    Run the command for your computer:
                                                    <div className="mt-3 flex flex-col gap-3">
                                                        {([
                                                            { id: 'unix' as McpOs, title: 'macOS / Linux — Terminal', help: `Open Terminal (on macOS: Applications → Utilities), paste the command, replace ${PASSWORD_PLACEHOLDER}, then press Enter.` },
                                                            { id: 'windows' as McpOs, title: 'Windows — PowerShell', help: `Open PowerShell (Start → type PowerShell), paste the command, replace ${PASSWORD_PLACEHOLDER}, then press Enter.` },
                                                        ]).map((item) => (
                                                            <CommandCard key={item.id} os={item.id} title={item.title} help={item.help} command={encodeCommand(item.id, authUser.trim() || settings.username)} current={os === item.id} />
                                                        ))}
                                                    </div>
                                                </li>
                                                <li>Replace <code className="rounded bg-white px-1 text-xs break-all">{AUTH_PLACEHOLDER}</code> in the configuration with the result.</li>
                                            </ol>
                                            <ul className="mt-3 mb-0 list-disc space-y-1 pl-5 text-[13px] text-slate-700">
                                                <li>Use printf, not echo: echo adds a newline and the value will not work.</li>
                                                <li>Keep the single quotes, so spaces and special characters stay intact.</li>
                                            </ul>
                                            <p className="mt-4 mb-0 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[13px] text-amber-900">
                                                The Authorization value is your password in another form (Base64 is not encryption). Keep it private.
                                            </p>

                                            <div className="mt-5 border-t border-blue-100 pt-4">
                                                <h4 className="m-0 text-sm font-semibold text-slate-900">Or create it here</h4>
                                                <p className="mt-1 mb-3 text-xs text-slate-600">Worked out in your browser and put into the configuration above; nothing is sent or saved.</p>
                                                <div className="grid max-w-xl gap-3">
                                                    <div>
                                                        <label htmlFor="tsmlt-mcp-auth-user" className="mb-1 block text-[13px] font-semibold text-slate-800">Username</label>
                                                        <input id="tsmlt-mcp-auth-user" type="text" autoComplete="off" spellCheck={false} className="h-9 w-full rounded-md border border-slate-300 bg-white px-3 text-sm" value={authUser} onChange={(event) => setAuthUser(event.target.value)} />
                                                    </div>
                                                    <div>
                                                        <label htmlFor="tsmlt-mcp-auth-password" className="mb-1 block text-[13px] font-semibold text-slate-800">Application password</label>
                                                        <input id="tsmlt-mcp-auth-password" type="password" autoComplete="new-password" spellCheck={false} className="h-9 w-full rounded-md border border-slate-300 bg-white px-3 text-sm" value={authPassword} onChange={(event) => setAuthPassword(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') { event.preventDefault(); createAuth(); } }} />
                                                    </div>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <button
                                                            type="button"
                                                            className="inline-flex h-9 cursor-pointer items-center rounded-lg border-0 bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600"
                                                            disabled={!authUser.trim() || !authPassword.trim()}
                                                            onClick={createAuth}
                                                        >
                                                            Create Authorization value
                                                        </button>
                                                        {encoded && (
                                                            <button type="button" className="inline-flex h-9 cursor-pointer items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50" onClick={() => setEncoded('')}>
                                                                Clear
                                                            </button>
                                                        )}
                                                    </div>
                                                    {encoded && (
                                                        <p className="m-0 rounded-lg bg-emerald-50 px-3 py-2 text-[13px] text-emerald-800" role="status">
                                                            Done: the Authorization value is now in the configuration above. Copy the configuration.
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>

                                <Step number={3} title="Test the connection" description={`After setup, paste this into ${clientName}. If it lists the Media Library Tools tools and the search works, the connection is ready. This page cannot see your client, so it does not report the connection itself.`}>
                                    <div className="flex items-start gap-2 rounded-lg border border-slate-200 bg-slate-50 py-2 pr-1.5 pl-3">
                                        <p className="m-0 min-w-0 flex-1 py-0.5 text-[13px] text-slate-800">{TEST_PROMPT}</p>
                                        <CopyToClipboard text={TEST_PROMPT} label="Copy test prompt" />
                                    </div>
                                </Step>
                            </div>
                        </div>
                    </section>

                    {/* Advanced configuration */}
                    <section aria-labelledby="tsmlt-mcp-advanced-title">
                        <h2 id="tsmlt-mcp-advanced-title" className="m-0 mb-1 text-xs font-semibold tracking-wider text-slate-500 uppercase">Advanced configuration</h2>
                        <p className="mt-0 mb-3 text-[13px] text-slate-600">For a connection to Media Library Tools only.</p>
                        <div className={`${CARD} overflow-hidden`}>
                            <Disclosure
                                id="tsmlt-mcp-dedicated"
                                title="Dedicated Media Library Tools MCP server"
                                description="An alternative endpoint that offers only the Media Library Tools tools."
                                open={dedicated}
                                onToggle={() => setDedicated(!dedicated)}
                            >
                                <UrlBox url={settings.dedicatedUrl} />
                                <ul className="mt-3 mb-0 list-disc space-y-1 pl-5 text-[13px] text-slate-600">
                                    <li>Only Media Library Tools' MCP tools, not other WordPress or plugin MCP abilities.</li>
                                    <li>Uses the same sign-in: replace the URL in the manual configuration above with this one.</li>
                                    <li>Also served by an MCP Adapter, so it answers whenever one is running on the site (the MCP Adapter plugin, or a plugin that bundles it).</li>
                                    <li>Use one of the two connections, not both. Details are on the <a href={settings.docsUrl} className="text-blue-700 underline">Documentation tab</a>.</li>
                                </ul>
                            </Disclosure>
                        </div>
                    </section>
                </>
            )}
        </div>
    );
}
