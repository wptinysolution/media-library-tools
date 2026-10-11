import { useEffect, useState } from 'react';
import { useStore, type AiKeyProvider } from '@/js/Utils/store';
import SettingRow from '@/js/Component/Common/SettingRow';
import ProLabel from "@/js/Component/Badges/ProLabel";

const EyeIcon = () => (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
    </svg>
);

const EyeOffIcon = () => (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
    </svg>
);

// Kept in sync with AiApi::LOCALE_LANGUAGES on the PHP side.
const AI_LANGUAGES = [
    'English', 'German', 'French', 'Spanish', 'Italian', 'Dutch', 'Portuguese',
    'Polish', 'Swedish', 'Danish', 'Norwegian', 'Finnish', 'Czech', 'Slovak',
    'Hungarian', 'Romanian', 'Greek', 'Turkish', 'Russian', 'Ukrainian',
    'Arabic', 'Hebrew', 'Hindi', 'Bengali', 'Indonesian', 'Vietnamese',
    'Thai', 'Japanese', 'Korean', 'Chinese',
] as const;

type ModelOption = { id: string; label?: string };
type ModelCatalogue = {
    defaultModel: string;
    groups: { label: string; models: ModelOption[] }[];
    // Saved values no longer offered, with a note on what happens to them.
    legacy: Record<string, string>;
};

// Model IDs and defaults are kept in sync with AiApi::DEFAULT_MODELS and the
// retirement tables in AiApi.php (checked by tests/ModelCatalogueTest.php).
const MODEL_CATALOGUES: Record<AiKeyProvider, ModelCatalogue> = {
    // Chat Completions model list:
    // https://developers.openai.com/api/reference/resources/chat/subresources/completions/methods/create
    chatgpt: {
        defaultModel: 'gpt-6-luna',
        groups: [
            { label: 'GPT-6', models: [{ id: 'gpt-6-astra' }, { id: 'gpt-6.1-sol' }, { id: 'gpt-6-luna' }] },
            { label: 'GPT-5', models: [{ id: 'gpt-5.6-sol' }, { id: 'gpt-5.6-terra' }, { id: 'gpt-5.6-luna' }, { id: 'gpt-5.5' }] },
            { label: 'GPT-4o / GPT-4.1 (Legacy)', models: [{ id: 'gpt-4o-mini' }, { id: 'gpt-4o' }, { id: 'gpt-4.1' }, { id: 'gpt-4.1-mini' }] },
        ],
        legacy: {},
    },
    // https://ai.google.dev/gemini-api/docs/models and /deprecations
    gemini: {
        defaultModel: 'gemini-3.5-flash-lite',
        groups: [
            { label: 'Gemini 3', models: [{ id: 'gemini-3.5-flash-lite' }, { id: 'gemini-3.6-flash' }, { id: 'gemini-3.8-flash' }] },
            {
                label: 'Gemini 2.5 (only for Google accounts that used them before)',
                models: [{ id: 'gemini-2.5-flash' }, { id: 'gemini-2.5-flash-lite' }, { id: 'gemini-2.5-pro' }],
            },
        ],
        legacy: {
            'gemini-3.5-flash': 'deprecated — now uses gemini-3.6-flash',
            'gemini-3.7-flash': 'deprecated — now uses gemini-3.8-flash',
        },
    },
    // https://platform.claude.com/docs/en/about-claude/models/overview and /model-deprecations
    claude: {
        defaultModel: 'claude-haiku-5-5',
        groups: [
            { label: 'Claude 5.5', models: [{ id: 'claude-haiku-5-5' }, { id: 'claude-sonnet-5-5' }, { id: 'claude-opus-5-5' }] },
            {
                label: 'Claude 4',
                models: [{ id: 'claude-haiku-4-5-20251001', label: 'claude-haiku-4-5' }, { id: 'claude-sonnet-4-6' }, { id: 'claude-opus-4-6' }],
            },
        ],
        legacy: {
            'claude-sonnet-4-5': 'retires Nov 30, 2026 — then uses claude-sonnet-5-5',
            'claude-sonnet-4-5-20250929': 'retires Nov 30, 2026 — then uses claude-sonnet-5-5',
        },
    },
};

/**
 * Model dropdown for one provider. A saved model that is no longer offered
 * stays selected and is labelled, so the setting is never changed silently.
 */
function ModelSelect({ provider }: { provider: AiKeyProvider }) {
    const { options, setOptions } = useStore();
    const catalogue = MODEL_CATALOGUES[provider];
    const field = `ai_${provider}_model` as const;
    const value = (options[field] as string) || catalogue.defaultModel;
    const offered = catalogue.groups.flatMap((group) => group.models.map((model) => model.id));

    return (
        <select
            name={field}
            className="w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
            value={value}
            onChange={(e) => setOptions({ [field]: e.target.value })}
        >
            {!offered.includes(value) && (
                <option value={value}>{value} ({catalogue.legacy[value] ?? 'no longer offered'})</option>
            )}
            {catalogue.groups.map((group) => (
                <optgroup key={group.label} label={group.label}>
                    {group.models.map((model) => (
                        <option key={model.id} value={model.id}>
                            {(model.label ?? model.id) + (model.id === catalogue.defaultModel ? ' (default)' : '')}
                        </option>
                    ))}
                </optgroup>
            ))}
        </select>
    );
}

const AI_PROMPT_FIELDS = [
    { key: 'ai_instruction_title', label: 'Title', placeholder: 'e.g. Always include the product colour.' },
    { key: 'ai_instruction_alt_text', label: 'Alt Text', placeholder: 'e.g. Describe the setting for screen readers.' },
    { key: 'ai_instruction_caption', label: 'Caption', placeholder: 'e.g. Keep it to one short sentence.' },
    { key: 'ai_instruction_description', label: 'Description', placeholder: 'e.g. Mention the material and intended use.' },
    { key: 'ai_instruction_filename', label: 'Filename', placeholder: 'e.g. Start with the product category.' },
] as const;

// Same for every key and provider, so it reveals nothing about the saved key.
const SAVED_KEY_PLACEHOLDER = '••••••••••••••••••••••••';

const AI_KEY_PLACEHOLDERS: Record<AiKeyProvider, string> = {
    chatgpt: 'sk-...',
    gemini: 'AIza...',
    claude: 'sk-ant-...',
};

/**
 * API key field. A saved key is never sent to the browser, so the field shows
 * only its status and offers Replace or Remove; a typed key is sent once on save.
 */
function ApiKeyField({ provider }: { provider: AiKeyProvider }) {
    const { options, setOptions } = useStore();
    const [showKey, setShowKey] = useState(false);
    const [replacing, setReplacing] = useState(false);

    const keyField = `ai_${provider}_key` as const;
    const removeField = `ai_${provider}_key_remove` as const;
    const status = options.ai_key_status?.[provider] ?? 'empty';
    const typed = (options[keyField] as string) || '';
    const removing = !!options[removeField];

    // Fresh statuses arrive after every save; leave "replace" mode then.
    useEffect(() => {
        setReplacing(false);
    }, [options.ai_key_status]);

    if (removing) {
        return (
            <div className="flex flex-wrap items-center gap-3 text-sm">
                <span className="text-gray-700">This API key will be removed when you save.</span>
                <button type="button" className="text-blue-600 hover:underline cursor-pointer" onClick={() => setOptions({ [removeField]: false })}>
                    Undo
                </button>
            </div>
        );
    }

    if ('saved' === status && !replacing && '' === typed) {
        return (
            <div className="w-full max-w-md space-y-2">
                <div className="flex items-center gap-3">
                    {/* A fixed placeholder: the saved key, and its length, never reach the browser. */}
                    <input
                        type="text"
                        readOnly
                        aria-label="Saved API key (hidden)"
                        className="w-full px-3 py-2 text-sm tracking-widest border border-gray-300 rounded-md bg-gray-50 text-gray-500 cursor-default"
                        value={SAVED_KEY_PLACEHOLDER}
                    />
                    <span className="shrink-0 inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800">
                        Saved
                    </span>
                </div>
                <div className="flex flex-wrap items-center gap-3 text-sm">
                    <button type="button" className="text-blue-600 hover:underline cursor-pointer" onClick={() => setReplacing(true)}>
                        Replace
                    </button>
                    <button type="button" className="text-red-600 hover:underline cursor-pointer" onClick={() => setOptions({ [removeField]: true })}>
                        Remove
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="w-full max-w-md space-y-2">
            {'unreadable' === status && (
                <p className="m-0! text-sm text-red-700">
                    The saved API key can&apos;t be read, usually because this site&apos;s WordPress security keys changed. Enter the key again to keep using AI features.
                </p>
            )}
            <div className="relative w-full">
                <input
                    type={showKey ? 'text' : 'password'}
                    name={keyField}
                    autoComplete="off"
                    className="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder={AI_KEY_PLACEHOLDERS[provider]}
                    value={typed}
                    onChange={(e) => setOptions({ [keyField]: e.target.value })}
                />
                <button type="button" onClick={() => setShowKey(v => !v)} className="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 cursor-pointer">
                    {showKey ? <EyeOffIcon /> : <EyeIcon />}
                </button>
            </div>
            {('saved' === status || 'unreadable' === status) && (
                <div className="flex gap-3 text-sm">
                    {'saved' === status && (
                        <button type="button" className="text-blue-600 hover:underline cursor-pointer" onClick={() => { setReplacing(false); setOptions({ [keyField]: '' }); }}>
                            Keep saved key
                        </button>
                    )}
                    {'unreadable' === status && (
                        <button type="button" className="text-red-600 hover:underline cursor-pointer" onClick={() => setOptions({ [keyField]: '', [removeField]: true })}>
                            Remove unreadable key
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

export default function AiSettings() {
    const { options, setOptions, setGeneralData } = useStore();

    const provider = options.ai_provider ?? 'gemini';
    const isPro = !!tsmltParams.hasExtended;

    return (
        <div className="bg-white rounded-lg border border-gray-200">
            <div className="px-6 py-5 border-b border-gray-200">
                <h3 className="text-xl m-0! font-semibold text-gray-900">AI Settings</h3>
            </div>
            <div className="p-6 space-y-6">
                <SettingRow label="AI Provider:">
                    <div className="flex flex-wrap gap-6">
                        {(['chatgpt', 'gemini', 'claude'] as const).map((p) => (
                            <label key={p} className="inline-flex items-center gap-2 cursor-pointer">
                                <input
                                    type="radio"
                                    name="ai_provider"
                                    value={p}
                                    className="w-4 h-4 m-0! border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                    checked={provider === p}
                                    onChange={() => setOptions({ ai_provider: p })}
                                />
                                <span className="text-base text-gray-900">
                                    {p === 'chatgpt' ? 'ChatGPT' : p === 'gemini' ? 'Gemini' : 'Claude'}
                                </span>
                            </label>
                        ))}
                    </div>
                    <p className="text-sm text-gray-500 mt-0!">Select which AI provider to use. All API keys you enter are saved; only the active provider&apos;s key is used.</p>
                </SettingRow>

                <SettingRow label="Send Image to AI:" bordered>
                    <label className="inline-flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            className="w-4 h-4 m-0! border-gray-300 text-blue-600 focus:ring-blue-500 rounded cursor-pointer"
                            checked={options.ai_send_image ?? false}
                            onChange={(e) => {
                                if ((options.ai_max_suggestion_count ?? 1) <= 1 && e.target.checked) {
                                    setGeneralData({ openProModal: true });
                                } else {
                                    setOptions({ ai_send_image: e.target.checked });
                                }
                            }}
                        />
                        <span className="text-base text-gray-900">Send image data to AI (uses more API tokens)</span>
                    </label>
                    <p className="text-sm text-gray-500 mt-0!">When enabled, the actual image is base64-encoded and sent to the AI for visual analysis. When disabled, the AI generates content using text context only: site title, tagline, filename, and attached post title.</p>
                    {(options.ai_max_suggestion_count ?? 1) <= 1 && (
                        <p className="text-sm text-amber-600">Sending image data requires Pro.</p>
                    )}
                </SettingRow>

                <SettingRow label="Number of Suggestions:" bordered>
                    <div className="flex items-center gap-3">
                        <select
                            className="w-24 px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                            value={options.ai_suggestion_count ?? 5}
                            onChange={(e) => {
                                if ((options.ai_max_suggestion_count ?? 1) <= 1) {
                                    setGeneralData({ openProModal: true });
                                } else {
                                    setOptions({ ai_suggestion_count: parseInt(e.target.value, 10) });
                                }
                            }}
                        >
                            {Array.from({ length: 6 }, (_, i) => i + 5).map((n) => (
                                <option key={n} value={n}>{n}</option>
                            ))}
                        </select>
                        {(options.ai_max_suggestion_count ?? 1) <= 1 && <ProLabel /> }
                    </div>
                </SettingRow>

                <SettingRow label="Content Language:" bordered>
                    <select
                        name="ai_language"
                        className="w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                        value={(options.ai_language as string) || ''}
                        onChange={(e) => setOptions({ ai_language: e.target.value })}
                    >
                        <option value="">Site language (default)</option>
                        {AI_LANGUAGES.map((lang) => (
                            <option key={lang} value={lang}>{lang}</option>
                        ))}
                    </select>
                    <p className="text-sm text-gray-500 mt-0!">Language used for AI-generated titles, alt text, captions and descriptions. &quot;Site language&quot; follows your WordPress language setting. AI-generated filenames always stay ASCII-safe (for example, ä becomes ae).</p>
                </SettingRow>

                <SettingRow label={<>Custom Instructions: {!isPro && <ProLabel />}</>} bordered>
                    <p className="text-sm text-gray-500 mt-0!">Optional. Each instruction is added to the built-in prompt when generating that field, so it takes precedence over the defaults. Leave a field empty to use the defaults.</p>

                    <div className="space-y-3 w-full max-w-md">
                        {AI_PROMPT_FIELDS.map(({ key, label, placeholder }) => (
                            <div key={key}>
                                <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
                                <textarea
                                    name={key}
                                    rows={2}
                                    readOnly={!isPro}
                                    // Click only — firing on focus would open the modal when
                                    // tabbing past the field, interrupting keyboard users.
                                    onClick={() => { if (!isPro) setGeneralData({ openProModal: true }); }}
                                    className={`w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${isPro ? '' : 'bg-gray-100 text-gray-400 cursor-pointer'}`}
                                    placeholder={isPro ? placeholder : 'Custom instructions require Pro.'}
                                    value={(options[key] as string) || ''}
                                    onChange={(e) => {
                                        if (!isPro) {
                                            setGeneralData({ openProModal: true });
                                            return;
                                        }
                                        setOptions({ [key]: e.target.value });
                                    }}
                                />
                            </div>
                        ))}
                    </div>
                </SettingRow>

                {provider === 'chatgpt' && (
                    <>
                        <SettingRow label="ChatGPT API Key:" bordered>
                            <ApiKeyField provider="chatgpt" />
                        </SettingRow>
                        <SettingRow label="ChatGPT Model:" bordered>
                            <ModelSelect provider="chatgpt" />
                        </SettingRow>
                    </>
                )}

                {provider === 'gemini' && (
                    <>
                        <SettingRow label="Gemini API Key:" bordered>
                            <ApiKeyField provider="gemini" />
                        </SettingRow>
                        <SettingRow label="Gemini Model:" bordered>
                            <ModelSelect provider="gemini" />
                        </SettingRow>
                    </>
                )}

                {provider === 'claude' && (
                    <>
                        <SettingRow label="Claude API Key:" bordered>
                            <ApiKeyField provider="claude" />
                        </SettingRow>
                        <SettingRow label="Claude Model:" bordered>
                            <ModelSelect provider="claude" />
                        </SettingRow>
                    </>
                )}
            </div>
        </div>
    );
}
