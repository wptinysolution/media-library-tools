import { useState } from "react";
import { Toaster } from "react-hot-toast";
import { CopyToClipboard } from "@/js/Component/CopyToClipboard";
import { createMcpCredentials, notifications, saveMcpAccess } from "@/js/Utils/Data";
import {
    MCP_CLIENTS,
    TEST_PROMPT,
    clientConfig,
    mcpClient,
    setupPrompt,
    type McpClientId,
    type McpCredentials,
} from "@/js/Utils/mcpConnect";

/** A code block with a copy button. */
function CodeBox({ text, placeholder }: { text: string; placeholder?: string }) {
    return (
        <div className="relative w-full min-w-0 rounded-lg border border-gray-200 bg-gray-50">
            {text && (
                <div className="absolute top-2 right-2">
                    <CopyToClipboard text={text} />
                </div>
            )}
            <pre className={`m-0 p-4 pr-12 text-xs leading-relaxed whitespace-pre-wrap break-all min-h-[220px] ${text ? 'text-gray-800' : 'text-gray-400'}`}>
                {text || placeholder}
            </pre>
        </div>
    );
}

/**
 * Media › MCP: turn MCP access off or on, and connect an MCP client to the
 * WordPress default MCP server with a generated setup prompt or manual
 * configuration.
 */
export default function McpPage() {
    const [settings, setSettings] = useState<McpPageSettings | null>(tsmltParams.mcp ?? null);
    const [saving, setSaving] = useState(false);
    const [client, setClient] = useState<McpClientId>('claude-code');
    const [manual, setManual] = useState(false);
    const [consent, setConsent] = useState(false);
    const [creating, setCreating] = useState(false);
    const [error, setError] = useState('');
    const [credentials, setCredentials] = useState<Partial<Record<McpClientId, McpCredentials>>>({});

    if (!settings) {
        return <div className="p-6 text-sm text-gray-600">MCP settings are not available.</div>;
    }

    const current = mcpClient(client);
    const creds = credentials[client];
    const ready = settings.abilitiesSupported && settings.adapterStatus === 'active' && settings.defaultServerSupported;
    const canGenerate = settings.enabled && ready && settings.canCreatePassword && consent && !creating;
    const clientName = client === 'other' ? 'another AI tool' : current.label;

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
        if (!canGenerate) return;
        setCreating(true);
        setError('');
        const result = await createMcpCredentials(client);
        setCreating(false);
        if (result.credentials) {
            setCredentials({ ...credentials, [client]: result.credentials });
        } else {
            setError(result.error || 'The Application Password could not be created.');
        }
    };

    const selectClient = (id: McpClientId) => {
        setClient(id);
        setError('');
        setConsent(false);
    };

    return (
        <div className="mx-auto px-6 py-8 min-h-screen bg-gray-50">
            <Toaster position="top-right" />
            <div className="max-w-[1200px] mx-auto">
                <div className="text-center mb-8">
                    <h1 className="text-3xl font-bold text-gray-900 m-0">MCP</h1>
                    <p className="text-base text-gray-600 mt-2 mb-0">
                        Connect an AI assistant to this site so it can search, inspect and manage your media library.{' '}
                        <a href={settings.docsUrl} className="text-blue-600 hover:underline">What is MCP?</a>
                    </p>
                </div>

                {/* MCP access */}
                <div className="bg-white rounded-lg border border-gray-200 p-5 mb-6">
                    <label className="flex items-start gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            className="mt-1 w-4 h-4"
                            checked={!settings.enabled}
                            disabled={saving}
                            onChange={(event) => toggleAccess(event.target.checked)}
                        />
                        <span>
                            <span className="block text-sm font-semibold text-gray-900">Turn off MCP access</span>
                            <span className="block text-sm text-gray-600 mt-1">
                                When checked, AI assistants cannot use any Media Library Tools tool or resource, through the WordPress default MCP server or the dedicated endpoint. Connected clients keep their settings and work again when you uncheck it. Other plugins' MCP tools are not affected.
                            </span>
                        </span>
                    </label>
                </div>

                {!settings.enabled && (
                    <div className="mb-6 p-4 rounded-lg border border-amber-200 bg-amber-50 text-sm text-amber-800">
                        MCP access is turned off. Turn it on to connect a client.
                    </div>
                )}
                {!ready && (
                    <div className="mb-6 p-4 rounded-lg border border-amber-200 bg-amber-50 text-sm text-amber-800">
                        The MCP Adapter plugin (and WordPress 6.9 or newer) is needed for MCP. See{' '}
                        <a href={settings.docsUrl} className="underline">MCP Docs</a> to install and activate it.
                    </div>
                )}
                {settings.enabled && ready && !settings.canCreatePassword && (
                    <div className="mb-6 p-4 rounded-lg border border-amber-200 bg-amber-50 text-sm text-amber-800">
                        {settings.passwordWarning} The setup prompt needs an Application Password, so this connection cannot be set up yet.
                    </div>
                )}

                {/* Recommended connection URL */}
                <div className="bg-white rounded-lg border border-gray-200 p-5 mb-8">
                    <div className="flex items-center gap-2 mb-2">
                        <span className="text-sm font-semibold text-gray-900">Connection URL</span>
                        <span className="px-2 py-0.5 text-xs font-medium text-green-700 bg-green-100 rounded-full">Recommended</span>
                    </div>
                    <div className="flex items-center gap-2 p-2 rounded-md border border-gray-200 bg-gray-50">
                        <code className="flex-1 text-sm break-all bg-transparent">{settings.serverUrl}</code>
                        <CopyToClipboard text={settings.serverUrl} />
                    </div>
                    <p className="text-xs text-gray-500 mt-2 mb-0">
                        The WordPress default MCP server: one connection for Media Library Tools and other MCP-enabled plugins. Sign in with your WordPress username and an Application Password; the steps below set this up for you.
                    </p>
                </div>

                {/* Client tabs */}
                <div className="flex justify-center mb-8">
                    <div className="inline-flex flex-wrap gap-1 p-1 rounded-full border border-gray-200 bg-white">
                        {MCP_CLIENTS.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                className={`px-5 py-2 text-sm rounded-full cursor-pointer transition-colors ${client === item.id ? 'font-semibold text-gray-900 border border-gray-300 bg-white shadow-sm' : 'text-gray-500 border border-transparent hover:text-gray-800'}`}
                                onClick={() => selectClient(item.id)}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="grid gap-8 md:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] items-start mb-10">
                    <div>
                        <h2 className="text-xl font-medium text-gray-900 mt-0 mb-3">Connect this site to {clientName}</h2>
                        {manual ? (
                            <p className="text-sm text-gray-600 leading-relaxed">
                                {current.format === 'shell'
                                    ? 'Run this command in your terminal, then run "claude mcp list" to check the connection.'
                                    : current.configPath.unix
                                        ? `Add this to ${current.configPath.unix} (macOS/Linux) or ${current.configPath.windows} (Windows), keeping any servers already there, then restart ${current.label}.`
                                        : 'Add this server to your client\'s MCP configuration (most clients accept this format), then restart the client.'}
                                {current.requiresNode && ' It needs Node.js 18 or newer.'}
                            </p>
                        ) : (
                            <p className="text-sm text-gray-600 leading-relaxed">
                                Paste this prompt into {clientName} to connect your site. As part of this process, an application password will be created to enable the connection.
                            </p>
                        )}
                        <p className="text-xs text-gray-500 leading-relaxed">
                            {manual
                                ? 'The configuration contains a password: keep it private.'
                                : 'The prompt contains the password, so it is sent to your AI tool. To keep the password out of the chat, use manual configuration instead.'}
                            {' '}Each client gets its own password, which you can revoke any time in <a href={settings.profileUrl} className="underline">your profile</a>.
                        </p>

                        {!creds && (
                            <>
                                <label className="flex items-start gap-3 my-4 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        className="mt-1 w-4 h-4"
                                        checked={consent}
                                        disabled={!settings.enabled || !ready || !settings.canCreatePassword}
                                        onChange={(event) => setConsent(event.target.checked)}
                                    />
                                    <span className="text-sm text-gray-700">
                                        I understand that clicking <strong>{manual ? 'Generate Configuration' : 'Generate Prompt'}</strong> will create an application password for this site.
                                    </span>
                                </label>
                                <button
                                    type="button"
                                    className="w-full max-w-[380px] px-5 py-3 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    onClick={generate}
                                    disabled={!canGenerate}
                                >
                                    {creating ? 'Generating…' : (manual ? 'Generate Configuration' : 'Generate Prompt')}
                                </button>
                            </>
                        )}
                        {error && <p className="text-sm text-red-600 mt-3 mb-0">{error}</p>}

                        <button
                            type="button"
                            className="block mt-5 text-sm text-blue-600 hover:underline cursor-pointer bg-transparent border-0 p-0"
                            onClick={() => setManual(!manual)}
                        >
                            {manual ? 'Prefer a setup prompt?' : 'Prefer manual configuration?'}
                        </button>
                    </div>

                    <CodeBox
                        text={creds ? (manual ? clientConfig(client, creds) : setupPrompt(client, creds)) : ''}
                        placeholder={manual ? 'Your configuration will appear here after you generate it.' : 'Your setup prompt will appear here after you generate it.'}
                    />
                </div>

                {/* Test */}
                <div className="bg-white rounded-lg border border-gray-200 p-5 mb-6">
                    <h2 className="text-base font-semibold text-gray-900 mt-0 mb-2">Test the connection</h2>
                    <p className="text-sm text-gray-600 mt-0">
                        After setup, paste this into {clientName}. If it lists Media Library Tools tools and the search works, the connection is ready. This page cannot see your client, so it does not report the connection itself.
                    </p>
                    <div className="flex items-start gap-2 p-3 rounded-md border border-gray-200 bg-gray-50">
                        <p className="flex-1 m-0 text-sm text-gray-800">{TEST_PROMPT}</p>
                        <CopyToClipboard text={TEST_PROMPT} />
                    </div>
                </div>

                <details className="text-sm text-gray-600">
                    <summary className="cursor-pointer">Advanced</summary>
                    <p>
                        Media Library Tools also keeps its earlier dedicated endpoint for existing connections. New connections should use the connection URL above. Details, troubleshooting and the full tool list are in <a href={settings.docsUrl} className="underline">MCP Docs</a>.
                    </p>
                </details>
            </div>
        </div>
    );
}
