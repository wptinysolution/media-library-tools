import { useState } from 'react';
import { useStore } from '@/js/Utils/store';
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

export default function AiSettings() {
    const { options, setOptions, setGeneralData } = useStore();
    const [showKey, setShowKey] = useState(false);

    const provider = options.ai_provider ?? 'gemini';

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

                {provider === 'chatgpt' && (
                    <>
                        <SettingRow label="ChatGPT API Key:" bordered>
                            <div className="relative w-full max-w-md">
                                <input
                                    type={showKey ? 'text' : 'password'}
                                    name="ai_chatgpt_key"
                                    className="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="sk-..."
                                    value={(options.ai_chatgpt_key as string) || ''}
                                    onChange={(e) => setOptions({ ai_chatgpt_key: e.target.value })}
                                />
                                <button type="button" onClick={() => setShowKey(v => !v)} className="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 cursor-pointer">
                                    {showKey ? <EyeOffIcon /> : <EyeIcon />}
                                </button>
                            </div>
                        </SettingRow>
                        <SettingRow label="ChatGPT Model:" bordered>
                            <select
                                name="ai_chatgpt_model"
                                className="w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                                value={(options.ai_chatgpt_model as string) || 'gpt-4o-mini'}
                                onChange={(e) => setOptions({ ai_chatgpt_model: e.target.value })}
                            >
                                <optgroup label="GPT-5">
                                    <option value="gpt-5.1">gpt-5.1</option>
                                    <option value="gpt-5-mini">gpt-5-mini</option>
                                </optgroup>
                                <optgroup label="GPT-4o">
                                    <option value="gpt-4o-mini">gpt-4o-mini (default)</option>
                                    <option value="gpt-4o">gpt-4o</option>
                                    <option value="gpt-4o-mini-search-preview">gpt-4o-mini-search-preview</option>
                                    <option value="gpt-4o-search-preview">gpt-4o-search-preview</option>
                                </optgroup>
                                <optgroup label="GPT-4.1">
                                    <option value="gpt-4.1">gpt-4.1</option>
                                    <option value="gpt-4.1-mini">gpt-4.1-mini</option>
                                    <option value="gpt-4.1-nano">gpt-4.1-nano</option>
                                </optgroup>
                                <optgroup label="GPT-4">
                                    <option value="gpt-4-turbo">gpt-4-turbo</option>
                                    <option value="gpt-4">gpt-4</option>
                                </optgroup>
                                <optgroup label="o-series (Reasoning)">
                                    <option value="o4-mini">o4-mini</option>
                                    <option value="o3">o3</option>
                                    <option value="o3-mini">o3-mini</option>
                                    <option value="o1">o1</option>
                                    <option value="o1-mini">o1-mini</option>
                                </optgroup>
                            </select>
                        </SettingRow>
                    </>
                )}

                {provider === 'gemini' && (
                    <>
                        <SettingRow label="Gemini API Key:" bordered>
                            <div className="relative w-full max-w-md">
                                <input
                                    type={showKey ? 'text' : 'password'}
                                    name="ai_gemini_key"
                                    className="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="AIza..."
                                    value={(options.ai_gemini_key as string) || ''}
                                    onChange={(e) => setOptions({ ai_gemini_key: e.target.value })}
                                />
                                <button type="button" onClick={() => setShowKey(v => !v)} className="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 cursor-pointer">
                                    {showKey ? <EyeOffIcon /> : <EyeIcon />}
                                </button>
                            </div>
                        </SettingRow>
                        <SettingRow label="Gemini Model:" bordered>
                            <select
                                name="ai_gemini_model"
                                className="w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                                value={(options.ai_gemini_model as string) || 'gemini-2.5-flash'}
                                onChange={(e) => setOptions({ ai_gemini_model: e.target.value })}
                            >
                                <option value="gemini-2.5-flash">gemini-2.5-flash (default)</option>
                                <option value="gemini-2.5-flash-lite">gemini-2.5-flash-lite</option>
                                <option value="gemini-2.5-pro">gemini-2.5-pro</option>
                            </select>
                        </SettingRow>
                    </>
                )}

                {provider === 'claude' && (
                    <>
                        <SettingRow label="Claude API Key:" bordered>
                            <div className="relative w-full max-w-md">
                                <input
                                    type={showKey ? 'text' : 'password'}
                                    name="ai_claude_key"
                                    className="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="sk-ant-..."
                                    value={(options.ai_claude_key as string) || ''}
                                    onChange={(e) => setOptions({ ai_claude_key: e.target.value })}
                                />
                                <button type="button" onClick={() => setShowKey(v => !v)} className="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 cursor-pointer">
                                    {showKey ? <EyeOffIcon /> : <EyeIcon />}
                                </button>
                            </div>
                        </SettingRow>
                        <SettingRow label="Claude Model:" bordered>
                            <select
                                name="ai_claude_model"
                                className="w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                                value={(options.ai_claude_model as string) || 'claude-haiku-4-5-20251001'}
                                onChange={(e) => setOptions({ ai_claude_model: e.target.value })}
                            >
                                <option value="claude-haiku-4-5-20251001">claude-haiku-4-5 (default)</option>
                                <option value="claude-sonnet-4-5">claude-sonnet-4-5</option>
                                <option value="claude-sonnet-4-6">claude-sonnet-4-6</option>
                                <option value="claude-opus-4-6">claude-opus-4-6</option>
                            </select>
                        </SettingRow>
                    </>
                )}
            </div>
        </div>
    );
}
